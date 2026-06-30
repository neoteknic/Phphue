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
 * DELETE /clip/v2/resource/{type}/{id} - remove a resource.
 */
class DeleteResource implements CommandInterface
{
    public function __construct(protected string $type, protected string $id)
    {
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
     * @return array<int,\stdClass> Deleted resource identifiers
     */
    public function send(Client $client): array
    {
        return $client->getTransport()->sendRequest(
            "/clip/v2/resource/{$this->type}/{$this->id}",
            TransportInterface::METHOD_DELETE
        );
    }
}
