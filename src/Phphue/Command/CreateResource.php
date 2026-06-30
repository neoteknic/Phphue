<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Command;

use Phphue\Client;
use Phphue\Transport\TransportInterface;

/**
 * POST /clip/v2/resource/{type} - create a new resource.
 */
class CreateResource implements CommandInterface
{
    /**
     * @param array<string,mixed>|object $body
     */
    public function __construct(protected readonly string $type, protected readonly array|object $body)
    {
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<int,\stdClass> Created resource identifiers
     */
    #[\Override]
    public function send(Client $client): array
    {
        return $client->getTransport()->sendRequest(
            "/clip/v2/resource/{$this->type}",
            TransportInterface::METHOD_POST,
            $this->body
        );
    }
}
