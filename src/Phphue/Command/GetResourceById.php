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
use Phphue\Transport\Exception\NotFoundException;
use Phphue\Transport\TransportInterface;

/**
 * GET /clip/v2/resource/{type}/{id} - fetch a single resource.
 */
class GetResourceById implements CommandInterface
{
    public function __construct(protected readonly string $type, protected readonly string $id)
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

    #[\Override]
    public function send(Client $client): AbstractResource
    {
        $data = $client->getTransport()->sendRequest(
            "/clip/v2/resource/{$this->type}/{$this->id}",
            TransportInterface::METHOD_GET
        );

        if (! isset($data[0])) {
            throw new NotFoundException("Resource {$this->type}/{$this->id} not found");
        }

        return ResourceFactory::create($client, $data[0]);
    }
}
