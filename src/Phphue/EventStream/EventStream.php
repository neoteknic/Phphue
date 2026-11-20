<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\EventStream;

use CurlHandle;
use Phphue\Client;
use Phphue\Transport\Adapter\Curl;
use Phphue\Transport\Exception\ConnectionException;
use Phphue\Transport\Http;

/**
 * Consumer for the CLIP v2 Server-Sent Events stream (GET /eventstream/clip/v2).
 *
 * The bridge keeps the HTTP connection open and pushes resource changes as they
 * happen. This consumer parses the SSE frames and dispatches one {@see Event}
 * per event to the provided callback.
 *
 * Note: requires the cURL extension. The callback may return `false` to stop
 * listening and let {@see EventStream::listen()} return.
 */
class EventStream
{
    public const string PATH = '/eventstream/clip/v2';

    /** Connection timeout in seconds. */
    public const int CONNECT_TIMEOUT = 4;

    /** Seconds of silence before TCP keep-alive probes are sent. */
    public const int KEEPALIVE_IDLE = 10;

    /** Seconds between two unanswered TCP keep-alive probes. */
    public const int KEEPALIVE_INTERVAL = 5;

    /** Unanswered probes before the connection is considered dead (libcurl >= 8.9). */
    public const int KEEPALIVE_PROBES = 3;

    /** Bytes of a non-2xx response body kept to describe the failure. */
    protected const int MAX_ERROR_BODY = 8192;

    protected string $buffer = '';

    protected ?\Closure $onEvent = null;

    protected bool $stopRequested = false;

    /** HTTP status of the response being received, resolved on its first chunk. */
    protected ?int $status = null;

    protected string $errorBody = '';

    /**
     * @param int $maxSeconds Maximum listening duration in seconds (0 = no limit)
     */
    public function __construct(protected readonly Client $client, protected readonly int $maxSeconds = 0)
    {
        if (! extension_loaded('curl')) {
            throw new \BadFunctionCallException('The cURL extension is required for the event stream.');
        }
        if ($maxSeconds < 0) {
            throw new \InvalidArgumentException('Maximum listening duration must be at least 0');
        }
    }

    /**
     * Listen to the event stream until the callback returns false, the optional
     * time limit is reached or the connection drops.
     *
     * A bridge that disappears without closing the connection (power cut, network
     * loss) is detected through TCP keep-alive probes, so the call does not hang
     * forever even without a time limit.
     *
     * @param callable(Event):mixed $onEvent Called once per received event
     *
     * @throws ConnectionException
     */
    public function listen(callable $onEvent): void
    {
        $this->onEvent = $onEvent(...);
        $this->buffer = '';
        $this->errorBody = '';
        $this->status = null;
        $this->stopRequested = false;

        $curl = curl_init();
        if ($curl === false) {
            throw new ConnectionException('Could not initialise a cURL handle');
        }

        try {
            curl_setopt_array($curl, $this->buildCurlOptions());
            curl_setopt($curl, CURLOPT_WRITEFUNCTION, function (CurlHandle $handle, string $chunk): int {
                $this->status ??= (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

                if ($this->status < 200 || $this->status >= 300) {
                    // Never parse an error body as events; keep a bounded copy for the message.
                    $room = self::MAX_ERROR_BODY - strlen($this->errorBody);
                    if ($room > 0) {
                        $this->errorBody .= substr($chunk, 0, $room);
                    }

                    return strlen($chunk);
                }

                $this->consume($chunk);

                // Returning less than the chunk size aborts with CURLE_WRITE_ERROR.
                return $this->stopRequested ? 0 : strlen($chunk);
            });

            $ok = curl_exec($curl);

            $this->checkConnectionResult(
                $ok,
                curl_errno($curl),
                (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
                curl_error($curl)
            );
        } finally {
            $this->onEvent = null;
        }
    }

    protected function checkConnectionResult(string|bool $ok, int $errno, int $status, string $error): void
    {
        // An HTTP error is the most useful diagnostic, even when the transfer also failed.
        if ($status !== 0 && ($status < 200 || $status >= 300)) {
            $description = $this->describeErrorBody($this->errorBody);

            throw new ConnectionException(
                "Event stream returned HTTP {$status}" . ($description !== null ? ": {$description}" : ''),
                $status
            );
        }

        $requestedStop = $this->stopRequested && $errno === CURLE_WRITE_ERROR;
        // A non-zero status is a 2xx here: the stream was established before the limit hit.
        $durationReached = $this->maxSeconds > 0 && $status !== 0 && $errno === CURLE_OPERATION_TIMEDOUT;
        if ($ok === false && ! $requestedStop && ! $durationReached) {
            throw new ConnectionException("Event stream connection failed (curl errno {$errno}): {$error}", $errno);
        }
    }

    /**
     * Request the listener to stop after the current event.
     */
    public function stop(): void
    {
        $this->stopRequested = true;
    }

    /**
     * Append a received chunk and dispatch every complete frame it closes.
     */
    protected function consume(string $chunk): void
    {
        $this->buffer .= $chunk;
        $this->drainBuffer();
    }

    /**
     * Extract complete SSE frames from the buffer and dispatch their events.
     */
    protected function drainBuffer(): void
    {
        $buffer = $this->buffer;

        if (str_contains($buffer, "\r")) {
            $buffer = str_replace("\r\n", "\n", $buffer);
        }

        // SSE frames are separated by a blank line. Walk the buffer with an offset
        // so the remainder is copied once, not once per frame.
        $offset = 0;
        while (! $this->stopRequested && ($pos = strpos($buffer, "\n\n", $offset)) !== false) {
            $frame = substr($buffer, $offset, $pos - $offset);
            $offset = $pos + 2;

            $this->dispatchFrame($frame);
        }

        $this->buffer = $offset === 0 ? $buffer : substr($buffer, $offset);
    }

    protected function dispatchFrame(string $frame): void
    {
        $handler = $this->onEvent;

        if ($handler === null) {
            return;
        }

        $dataLines = [];

        foreach (explode("\n", $frame) as $line) {
            if (str_starts_with($line, 'data:')) {
                // Per the SSE spec, only a single leading space is part of the separator.
                $value = substr($line, 5);
                $dataLines[] = str_starts_with($value, ' ') ? substr($value, 1) : $value;
            }
        }

        if ($dataLines === []) {
            return;
        }

        $decoded = json_decode(implode("\n", $dataLines));

        if (! is_array($decoded)) {
            return;
        }

        foreach ($decoded as $payload) {
            if (! $payload instanceof \stdClass) {
                continue;
            }

            if ($handler(new Event($this->client, $payload)) === false) {
                $this->stopRequested = true;
            }

            if ($this->stopRequested) {
                return;
            }
        }
    }

    /**
     * cURL options of the streaming request (the write callback is added separately).
     *
     * @return array<int,mixed>
     */
    protected function buildCurlOptions(): array
    {
        [$verify, $caFile] = $this->resolveTlsSettings();

        $options = [
            CURLOPT_URL => $this->buildUrl(),
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => $this->buildHeaders(),
            CURLOPT_TIMEOUT => $this->maxSeconds,
            CURLOPT_CONNECTTIMEOUT => static::CONNECT_TIMEOUT,
            CURLOPT_NOSIGNAL => true,
            // Detect a bridge that vanished without closing the connection.
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_TCP_KEEPIDLE => static::KEEPALIVE_IDLE,
            CURLOPT_TCP_KEEPINTVL => static::KEEPALIVE_INTERVAL,
            CURLOPT_SSL_VERIFYPEER => $verify,
            CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0,
        ];

        // Only exposed when PHP was built against libcurl >= 8.9.
        if (defined('CURLOPT_TCP_KEEPCNT')) {
            $options[CURLOPT_TCP_KEEPCNT] = static::KEEPALIVE_PROBES;
        }

        if ($verify && $caFile !== null && $caFile !== '') {
            $options[CURLOPT_CAINFO] = $caFile;
        }

        return $options;
    }

    /**
     * Apply the same TLS policy as the REST requests: when they go through the
     * cURL adapter, its verification flag and pinned CA bundle are reused.
     *
     * @return array{bool, string|null}
     */
    protected function resolveTlsSettings(): array
    {
        $transport = $this->client->getTransport();

        if ($transport instanceof Http) {
            $adapter = $transport->getAdapter();

            if ($adapter instanceof Curl) {
                return [$adapter->isSslVerify(), $adapter->getCaFile()];
            }
        }

        return [$this->client->getSslVerify(), null];
    }

    /**
     * Pull the CLIP error descriptions out of a non-2xx response body.
     */
    protected function describeErrorBody(string $body): ?string
    {
        $decoded = json_decode($body);

        if (! is_object($decoded) || ! isset($decoded->errors) || ! is_array($decoded->errors)) {
            return null;
        }

        $descriptions = [];
        foreach ($decoded->errors as $error) {
            if (is_object($error) && isset($error->description) && is_scalar($error->description)) {
                $descriptions[] = (string) $error->description;
            }
        }

        return $descriptions === [] ? null : implode('; ', $descriptions);
    }

    protected function buildUrl(): string
    {
        return $this->client->getBaseUrl() . self::PATH;
    }

    /**
     * @return array<int,string>
     */
    protected function buildHeaders(): array
    {
        $headers = ['Accept: text/event-stream'];

        if ($this->client->getApplicationKey() !== null) {
            $headers[] = 'hue-application-key: ' . $this->client->getApplicationKey();
        }

        return $headers;
    }
}
