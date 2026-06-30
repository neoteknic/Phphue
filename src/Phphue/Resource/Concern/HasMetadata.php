<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource\Concern;

/**
 * Shared accessors for resources carrying a `metadata` object (name, archetype).
 */
trait HasMetadata
{
    public function getName(): ?string
    {
        return isset($this->data->metadata->name) ? (string) $this->data->metadata->name : null;
    }

    public function getArchetype(): ?string
    {
        return isset($this->data->metadata->archetype) ? (string) $this->data->metadata->archetype : null;
    }

    /**
     * Rename the resource (PUT metadata.name).
     *
     * @return array<int,\stdClass>
     */
    public function setName(string $name): array
    {
        return $this->update(['metadata' => ['name' => $name]]);
    }
}
