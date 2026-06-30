<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Command;

use Phphue\Client;
use Phphue\Transport\Exception\HueException;
use Phphue\Transport\Exception\LinkButtonNotPressedException;
use Phphue\Transport\TransportInterface;

/**
 * POST /api - create an application key (and optionally a client key).
 *
 * This is the only endpoint that is still served by the legacy v1 envelope:
 *   success: [{"success":{"username":"...","clientkey":"..."}}]
 *   failure: [{"error":{"type":101,"description":"link button not pressed"}}]
 *
 * The bridge link button must be pressed within 30 seconds before sending.
 */
class CreateApplicationKey implements CommandInterface
{
    /** Bridge error type returned while the link button has not been pressed. */
    public const int ERROR_LINK_BUTTON = 101;

    public function __construct(
        protected readonly string $appName,
        protected readonly string $instanceName = 'php',
        protected readonly bool $generateClientKey = false
    ) {
    }

    #[\Override]
    public function send(Client $client): \stdClass
    {
        $body = [
            'devicetype' => "{$this->appName}#{$this->instanceName}",
        ];

        if ($this->generateClientKey) {
            $body['generateclientkey'] = true;
        }

        $response = $client->getTransport()->sendRaw(
            '/api',
            TransportInterface::METHOD_POST,
            $body
        );

        // Legacy envelope is an array with a single element.
        if (is_array($response)) {
            $response = array_first($response);
        }

        if (is_object($response) && isset($response->error)) {
            $type = (int) ($response->error->type ?? 0);
            $description = (string) ($response->error->description ?? 'Unknown error');

            if ($type === self::ERROR_LINK_BUTTON) {
                throw new LinkButtonNotPressedException($description, $type);
            }

            throw new HueException($description, $type);
        }

        if (is_object($response) && isset($response->success)) {
            return $response->success;
        }

        throw new HueException('Unexpected response while creating application key');
    }
}
