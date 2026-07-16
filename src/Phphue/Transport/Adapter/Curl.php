<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Transport\Adapter;

use CurlHandle;

/**
 * cURL Http adapter
 */
class Curl implements AdapterInterface
{
    /**
     * @var resource|false|CurlHandle|null
     */
    protected $curl;

    /**
     * @param bool $sslVerify   Verify the bridge TLS certificate (bridges use a self-signed cert)
     * @param int  $timeout     Request timeout in seconds (0 = no timeout, useful for the event stream)
     * @param string|null $caFile Optional CA bundle to pin the Hue bridge certificate
     */
    public function __construct(
        protected readonly bool $sslVerify = false,
        protected readonly int $timeout = 10,
        protected readonly ?string $caFile = null
    ) {
        if (! extension_loaded('curl')) {
            throw new \BadFunctionCallException('The cURL extension is required.');
        }
    }

    #[\Override]
    public function open(): void
    {
        if ($this->curl instanceof CurlHandle) {
            // Reuse the existing handle so its kept-alive TCP/TLS connection is
            // reused across requests. curl_reset() clears the options set by the
            // previous request - a stale PUT body must not leak into a following
            // GET - without dropping the pooled connection.
            curl_reset($this->curl);

            return;
        }

        $this->curl = curl_init();
    }

    #[\Override]
    public function send(string $address, string $method, ?string $body = null, array $headers = []): string|bool
    {
        curl_setopt($this->curl, CURLOPT_URL, $address);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($this->curl, CURLOPT_HEADER, false);
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
        // Keep the connection warm between requests to the same bridge.
        curl_setopt($this->curl, CURLOPT_TCP_KEEPALIVE, 1);

        if (preg_match('#^https://#i', $address) === 1) {
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, $this->sslVerify);
            curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, $this->sslVerify ? 2 : 0);

            if ($this->sslVerify && $this->caFile !== null) {
                curl_setopt($this->curl, CURLOPT_CAINFO, $this->caFile);
            }
        }

        $requestHeaders = array_merge(['Accept: application/json'], $headers);

        if ($body !== null && strlen($body)) {
            $requestHeaders[] = 'Content-Type: application/json';
            curl_setopt($this->curl, CURLOPT_POSTFIELDS, $body);
        }

        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $requestHeaders);

        return curl_exec($this->curl);
    }

    #[\Override]
    public function getHttpStatusCode(): int
    {
        return (int) curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
    }

    #[\Override]
    public function getContentType(): mixed
    {
        return curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE);
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
     */
    public function disconnect(): void
    {
        // curl_close() is a no-op since PHP 8.0 and the handle is freed on unset;
        // the minimum supported runtime is PHP 8.5, so simply drop the reference.
        $this->curl = null;
    }

    public function __destruct()
    {
        $this->disconnect();
    }
}
