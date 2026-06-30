<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Transport\Adapter;

/**
 * Http adapter interface
 */
interface AdapterInterface
{
    /**
     * Opens the connection
     */
    public function open(): void;

    /**
     * Sends a request
     *
     * @param string $address Full request URL
     * @param string $method  Request method
     * @param string|null $body Body data
     * @param array<int,string> $headers Raw HTTP headers (e.g. "hue-application-key: ...")
     *
     * @return string|bool Response body
     */
    public function send(string $address, string $method, ?string $body = null, array $headers = []): string|bool;

    /**
     * Get http status code from response
     */
    public function getHttpStatusCode(): int;

    /**
     * Get content type from response
     */
    public function getContentType(): mixed;

    /**
     * Closes the connection
     */
    public function close(): void;
}
