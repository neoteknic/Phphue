<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasMetadata;

/**
 * `geofence_client` resource - a presence client used for geofencing.
 */
class GeofenceClient extends AbstractResource
{
    use HasMetadata;

    public function isAtHome(): ?bool
    {
        return isset($this->data->is_at_home) ? (bool) $this->data->is_at_home : null;
    }
}
