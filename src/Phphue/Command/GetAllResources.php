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
 * GET /clip/v2/resource - list every resource on the bridge.
 */
class GetAllResources implements CommandInterface
{
    /**
     * @return AbstractResource[]
     */
    #[\Override]
    public function send(Client $client): array
    {
        $data = $client->getTransport()->sendRequest(
            '/clip/v2/resource',
            TransportInterface::METHOD_GET
        );

        return ResourceFactory::createCollection($client, $data);
    }
}
