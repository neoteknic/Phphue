<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `bridge` resource.
 */
class Bridge extends AbstractResource
{
    public function getBridgeId(): ?string
    {
        return isset($this->data->bridge_id) ? (string) $this->data->bridge_id : null;
    }

    public function getTimeZone(): ?string
    {
        return isset($this->data->time_zone->time_zone) ? (string) $this->data->time_zone->time_zone : null;
    }
}
