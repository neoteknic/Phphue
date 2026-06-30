<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `homekit` resource - Apple HomeKit pairing status of the bridge.
 */
class Homekit extends AbstractResource
{
    /**
     * Pairing status: "paired", "pairing" or "unpaired".
     */
    public function getStatus(): ?string
    {
        return isset($this->data->status) ? (string) $this->data->status : null;
    }
}
