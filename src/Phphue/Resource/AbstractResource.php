<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Client;

/**
 * Base class for every CLIP v2 resource.
 *
 * A resource is a thin, typed wrapper around the raw `\stdClass` returned by the
 * bridge. Sub classes add convenience accessors; everything is still reachable
 * through {@see AbstractResource::getRaw()} and {@see AbstractResource::attr()}.
 */
abstract class AbstractResource
{
    public function __construct(protected Client $client, protected \stdClass $data)
    {
    }

    public function getId(): string
    {
        return (string) ($this->data->id ?? '');
    }

    /**
     * Legacy v1 identifier (e.g. "/lights/3"), when the resource has one.
     */
    public function getIdV1(): ?string
    {
        return isset($this->data->id_v1) ? (string) $this->data->id_v1 : null;
    }

    public function getType(): string
    {
        return (string) ($this->data->type ?? '');
    }

    /**
     * Owning resource reference ({rid, rtype}), when present.
     */
    public function getOwner(): ?\stdClass
    {
        return $this->data->owner ?? null;
    }

    /**
     * Raw attributes exactly as returned by the bridge.
     */
    public function getRaw(): \stdClass
    {
        return $this->data;
    }

    /**
     * Read a top level attribute, returning $default when absent.
     */
    public function attr(string $key, mixed $default = null): mixed
    {
        return $this->data->{$key} ?? $default;
    }

    /**
     * PUT changes to this resource.
     *
     * @param array<string,mixed>|object $body
     * @return array<int,\stdClass> Resource identifiers returned by the bridge
     */
    public function update(array|object $body): array
    {
        return $this->client->updateResource($this->getType(), $this->getId(), $body);
    }

    /**
     * DELETE this resource.
     *
     * @return array<int,\stdClass>
     */
    public function delete(): array
    {
        return $this->client->deleteResource($this->getType(), $this->getId());
    }

    /**
     * Re-fetch this resource from the bridge and replace the local attributes.
     */
    public function refresh(): static
    {
        $fresh = $this->client->getResourceById($this->getType(), $this->getId());
        $this->data = $fresh->getRaw();

        return $this;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    public function __toString(): string
    {
        return $this->getId();
    }
}
