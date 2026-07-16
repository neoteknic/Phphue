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
     * A non 2xx HTTP status raises an exception. A non-empty `errors` array
     * returned with a 2xx status is treated as a soft warning: the bridge
     * accepted the request but is flagging an issue (e.g. a Zigbee device that
     * "may not have received" the command). Warnings do not raise an exception
     * unless {@see setThrowOnWarnings()} is enabled; they are exposed through
     * {@see getLastWarnings()} and the warning handler instead.
     *
     * @param string $address API path (e.g. "/clip/v2/resource/light")
     * @param string $method  Request method
     * @param array<string,mixed>|object|null $body Body data
     *
     * @return array<int,\stdClass> The "data" array returned by the bridge
     */
    public function sendRequest(string $address, string $method = self::METHOD_GET, array|object|null $body = null): array;

    /**
     * Choose whether soft warnings (a non-empty `errors` array returned with a
     * 2xx status) raise a HueException. Off by default: warnings are recorded
     * and dispatched to the warning handler instead of blocking the command.
     */
    public function setThrowOnWarnings(bool $throw): static;

    /**
     * Warnings collected during the most recent {@see sendRequest()} call.
     *
     * @return array<int,string>
     */
    public function getLastWarnings(): array;

    /**
     * Register a callback invoked with the warning descriptions whenever the
     * bridge returns soft warnings (and they are not configured to throw).
     *
     * @param (callable(array<int,string>):void)|null $handler
     */
    public function setWarningHandler(?callable $handler): static;

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
