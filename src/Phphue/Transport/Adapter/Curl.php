<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Transport\Adapter;

use CurlHandle;
use CurlSharePersistentHandle;
use Phphue\Transport\Exception\ConnectionException;

/**
 * cURL Http adapter
 */
class Curl implements AdapterInterface
{
    protected ?CurlHandle $curl = null;

    protected ?CurlSharePersistentHandle $share = null;

    /**
     * @param bool $sslVerify   Verify the bridge TLS certificate (bridges use a self-signed cert)
     * @param int  $timeout     Request timeout in seconds (0 = no timeout, useful for the event stream)
     * @param string|null $caFile Optional CA bundle to pin the Hue bridge certificate
     * @param bool $persistent  Keep the connection pool, DNS and TLS session caches in a persistent
     *                          cURL share handle, so successive PHP requests served by the same
     *                          worker (PHP-FPM, ...) reuse the bridge connection instead of paying
     *                          for a new TCP + TLS handshake. Each worker keeps one idle connection
     *                          open to the bridge: only enable it with a small worker pool.
     */
    public function __construct(
        protected bool $sslVerify = false,
        protected readonly int $timeout = 4,
        protected readonly ?string $caFile = null,
        protected readonly bool $persistent = false
    ) {
        if (! extension_loaded('curl')) {
            throw new \BadFunctionCallException('The cURL extension is required.');
        }
        if ($timeout < 0) {
            throw new \InvalidArgumentException('Timeout must be at least 0');
        }
    }

    public function setSslVerify(bool $sslVerify): void
    {
        if ($this->sslVerify !== $sslVerify) {
            // A connection established without verification must not be reused.
            // (A shared pool is safe too: libcurl only reuses a connection whose TLS
            // verification settings match the new request.)
            $this->disconnect();
            $this->sslVerify = $sslVerify;
        }
    }

    public function isSslVerify(): bool
    {
        return $this->sslVerify;
    }

    public function getCaFile(): ?string
    {
        return $this->caFile;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function isPersistent(): bool
    {
        return $this->persistent;
    }

    #[\Override]
    public function open(): void
    {
        if ($this->curl !== null) {
            // Reuse the existing handle so its kept-alive TCP/TLS connection is
            // reused across requests. curl_reset() clears the options set by the
            // previous request - a stale PUT body must not leak into a following
            // GET - without dropping the pooled connection.
            curl_reset($this->curl);

            return;
        }

        $this->curl = $this->createHandle();
    }

    #[\Override]
    public function send(string $address, string $method, ?string $body = null, array $headers = []): string|bool
    {
        if ($address === '' || $method === '') {
            throw new \InvalidArgumentException('Request address and method must not be empty');
        }

        // Tolerate callers that skip open(): the handle is created on demand.
        $curl = $this->curl ??= $this->createHandle();

        $requestHeaders = ['Accept: application/json', ...$headers];

        $options = [
            CURLOPT_URL => $address,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            // Never rely on SIGALRM for resolver timeouts (unsafe with threaded SAPIs).
            CURLOPT_NOSIGNAL => true,
            // Keep the connection warm between requests to the same bridge.
            CURLOPT_TCP_KEEPALIVE => 1,
        ];

        if ($this->persistent) {
            $options[CURLOPT_SHARE] = $this->share ??= curl_share_init_persistent([
                CURL_LOCK_DATA_CONNECT,
                CURL_LOCK_DATA_DNS,
                CURL_LOCK_DATA_SSL_SESSION,
            ]);
        }

        if (strncasecmp($address, 'https://', 8) === 0) {
            $options[CURLOPT_SSL_VERIFYPEER] = $this->sslVerify;
            $options[CURLOPT_SSL_VERIFYHOST] = $this->sslVerify ? 2 : 0;

            if ($this->sslVerify && $this->caFile !== null && $this->caFile !== '') {
                $options[CURLOPT_CAINFO] = $this->caFile;
            }
        }

        if ($body !== null && $body !== '') {
            $requestHeaders[] = 'Content-Type: application/json';
            // Without this, libcurl announces large bodies with "Expect: 100-continue"
            // and stalls up to one second waiting for an interim response.
            $requestHeaders[] = 'Expect:';
            $options[CURLOPT_POSTFIELDS] = $body;
        }

        $options[CURLOPT_HTTPHEADER] = $requestHeaders;

        if (! curl_setopt_array($curl, $options)) {
            throw new ConnectionException('Could not configure the cURL request: ' . curl_error($curl));
        }

        $result = curl_exec($curl);
        if ($result === false) {
            $errno = curl_errno($curl);
            $error = curl_error($curl);
            throw new ConnectionException("Hue request failed (curl errno {$errno}): {$error}", $errno);
        }

        return $result;
    }

    #[\Override]
    public function getHttpStatusCode(): int
    {
        return $this->curl === null ? 0 : (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
    }

    #[\Override]
    public function getContentType(): mixed
    {
        return $this->curl === null ? null : curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE);
    }

    public function getCurl(): false|CurlHandle|null
    {
        return $this->curl;
    }

    #[\Override]
    public function close(): void
    {
        // Intentionally a no-op: the handle - and its pooled TCP/TLS connection -
        // is kept alive so the next request to the same bridge reuses it instead
        // of paying for a fresh TCP + TLS handshake. The handle is released in
        // the destructor (or explicitly via disconnect()).
    }

    /**
     * Explicitly drop the persistent handle and close its connection.
     *
     * In persistent mode the connection lives in the shared pool and stays available.
     */
    public function disconnect(): void
    {
        // curl_close() is a no-op since PHP 8.0 (deprecated in 8.5) and the handle
        // is freed on unset, so simply drop the reference.
        $this->curl = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    protected function createHandle(): CurlHandle
    {
        $curl = curl_init();

        if ($curl === false) {
            throw new ConnectionException('Could not initialise a cURL handle');
        }

        return $curl;
    }
}
