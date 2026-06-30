<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource\Concern;

/**
 * Shared `enabled` accessors for sensor resources that can be turned on/off.
 */
trait HasEnabled
{
    public function isEnabled(): bool
    {
        return (bool) ($this->data->enabled ?? false);
    }

    /**
     * @return array<int,\stdClass>
     */
    public function setEnabled(bool $enabled = true): array
    {
        return $this->update(['enabled' => $enabled]);
    }
}
