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
        // curl_close() is a no-op since PHP 8.0 and the handle is freed on unset;
        // the minimum supported runtime is PHP 8.5, so simply drop the reference.
        $this->curl = null;
    }
}
