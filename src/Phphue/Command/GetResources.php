<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Command;

use Phphue\Client;
use Phphue\Resource\AbstractResource;
use Phphue\Resource\ResourceFactory;
use Phphue\Transport\TransportInterface;

/**
 * GET /clip/v2/resource/{type} - list resources of a given type.
 */
class GetResources implements CommandInterface
{
    public function __construct(protected readonly string $type)
    {
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return AbstractResource[]
     */
    #[\Override]
    public function send(Client $client): array
    {
        $data = $client->getTransport()->sendRequest(
            "/clip/v2/resource/{$this->type}",
            TransportInterface::METHOD_GET
        );

        return ResourceFactory::createCollection($client, $data);
    }
}
