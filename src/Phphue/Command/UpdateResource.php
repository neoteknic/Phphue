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
 * PUT /clip/v2/resource/{type}/{id} - update an existing resource.
 */
class UpdateResource implements CommandInterface
{
    /**
     * @param array<string,mixed>|object $body
     */
    public function __construct(
        protected readonly string $type,
        protected readonly string $id,
        protected readonly array|object $body
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<int,\stdClass> Updated resource identifiers
     */
    #[\Override]
    public function send(Client $client): array
    {
        return $client->getTransport()->sendRequest(
            "/clip/v2/resource/{$this->type}/{$this->id}",
            TransportInterface::METHOD_PUT,
            $this->body
        );
    }
}
