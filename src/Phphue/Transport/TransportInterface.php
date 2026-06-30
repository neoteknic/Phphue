<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Transport;

/**
 * Transport Interface
 */
interface TransportInterface
{
    public const string METHOD_GET = 'GET';

    public const string METHOD_POST = 'POST';

    public const string METHOD_PUT = 'PUT';

    public const string METHOD_DELETE = 'DELETE';

    /**
     * Send a CLIP v2 request and return the decoded "data" payload.
     *
     * The bridge always answers with `{ "errors": [...], "data": [...] }`.
     * A non-empty `errors` array (or a non 2xx HTTP status) raises an exception.
     *
     * @param string $address API path (e.g. "/clip/v2/resource/light")
     * @param string $method  Request method
     * @param array<string,mixed>|object|null $body Body data
     *
     * @return array<int,\stdClass> The "data" array returned by the bridge
     */
    public function sendRequest(string $address, string $method = self::METHOD_GET, array|object|null $body = null): array;

    /**
     * Send a request and return the decoded JSON body untouched.
     *
     * Used for endpoints that do not follow the CLIP v2 envelope, such as the
     * legacy `POST /api` used to create an application key.
     *
     * @param array<string,mixed>|object|null $body Body data
     *
     * @return mixed Decoded JSON (stdClass|array|scalar|null)
     */
    public function sendRaw(string $address, string $method = self::METHOD_GET, array|object|null $body = null): mixed;
}
