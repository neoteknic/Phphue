<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\EventStream;

use Phphue\Client;
use Phphue\Transport\Exception\ConnectionException;

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
    public const PATH = '/eventstream/clip/v2';

    protected string $buffer = '';

    /** @var callable|null */
    protected $onEvent;

    protected bool $stopRequested = false;

    /**
     * @param int $maxSeconds Maximum listening duration in seconds (0 = no limit)
     */
    public function __construct(protected Client $client, protected int $maxSeconds = 0)
    {
        if (! extension_loaded('curl')) {
            throw new \BadFunctionCallException('The cURL extension is required for the event stream.');
        }
    }

    /**
     * Listen to the event stream until the callback returns false, the optional
     * time limit is reached or the connection drops.
     *
     * @param callable(Event):mixed $onEvent Called once per received event
     *
     * @throws ConnectionException
     */
    public function listen(callable $onEvent): void
    {
        $this->onEvent = $onEvent;
        $this->buffer = '';
        $this->stopRequested = false;

        $curl = curl_init();
        $startedAt = time();

        curl_setopt($curl, CURLOPT_URL, $this->buildUrl());
        curl_setopt($curl, CURLOPT_HTTPGET, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->buildHeaders());
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->maxSeconds);

        $verify = $this->client->getSslVerify();
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);

        curl_setopt($curl, CURLOPT_WRITEFUNCTION, function ($handle, string $chunk) use ($startedAt): int {
            $this->buffer .= $chunk;
            $this->drainBuffer();

            if ($this->stopRequested) {
                return -1; // aborts curl_exec
            }

            if ($this->maxSeconds > 0 && (time() - $startedAt) >= $this->maxSeconds) {
                return -1;
            }

            return strlen($chunk);
        });

        $ok = curl_exec($curl);
        $errno = curl_errno($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if (PHP_VERSION_ID < 80500) {
            curl_close($curl);
        }

        // CURLE_ABORTED_BY_CALLBACK (42) and operation timeout (28) are expected stops.
        if ($ok === false && ! in_array($errno, [0, 28, 42], true)) {
            throw new ConnectionException('Event stream connection failed (curl errno ' . $errno . ')');
        }

        if ($status !== 0 && ($status < 200 || $status >= 300)) {
            throw new ConnectionException("Event stream returned HTTP {$status}");
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
     * Extract complete SSE frames from the buffer and dispatch their events.
     */
    protected function drainBuffer(): void
    {
        // SSE frames are separated by a blank line.
        $normalized = str_replace("\r\n", "\n", $this->buffer);

        while (($pos = strpos($normalized, "\n\n")) !== false) {
            $frame = substr($normalized, 0, $pos);
            $normalized = substr($normalized, $pos + 2);

            $this->dispatchFrame($frame);

            if ($this->stopRequested) {
                break;
            }
        }

        $this->buffer = $normalized;
    }

    protected function dispatchFrame(string $frame): void
    {
        $dataLines = [];

        foreach (explode("\n", $frame) as $line) {
            if (str_starts_with($line, 'data:')) {
                $dataLines[] = ltrim(substr($line, 5));
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
            if (! is_object($payload)) {
                continue;
            }

            $result = ($this->onEvent)(new Event($this->client, $payload));

            if ($result === false) {
                $this->stopRequested = true;

                return;
            }
        }
    }

    protected function buildUrl(): string
    {
        $host = rtrim($this->client->getHost(), '/');

        if (preg_match('#^https?://#i', $host) !== 1) {
            $host = "https://{$host}";
        }

        return $host . self::PATH;
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
