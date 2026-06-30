<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\EventStream;

use Phphue\Client;
use Phphue\Resource\AbstractResource;
use Phphue\Resource\ResourceFactory;

/**
 * A single event delivered by the CLIP v2 event stream.
 *
 * Each event has a type (add/update/delete/error) and carries a list of
 * resource snapshots in its `data` array.
 */
class Event
{
    public const TYPE_ADD = 'add';

    public const TYPE_UPDATE = 'update';

    public const TYPE_DELETE = 'delete';

    public const TYPE_ERROR = 'error';

    public function __construct(protected Client $client, protected \stdClass $payload)
    {
    }

    public function getType(): string
    {
        return (string) ($this->payload->type ?? '');
    }

    public function getId(): ?string
    {
        return isset($this->payload->id) ? (string) $this->payload->id : null;
    }

    public function getCreationTime(): ?string
    {
        return isset($this->payload->creationtime) ? (string) $this->payload->creationtime : null;
    }

    /**
     * Raw resource snapshots carried by this event.
     *
     * @return array<int,\stdClass>
     */
    public function getData(): array
    {
        $data = $this->payload->data ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * Resource snapshots wrapped as typed resources.
     *
     * @return AbstractResource[]
     */
    public function getResources(): array
    {
        return ResourceFactory::createCollection($this->client, $this->getData());
    }

    public function getRaw(): \stdClass
    {
        return $this->payload;
    }
}
