<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasEnabled;

/**
 * `light_level` resource - ambient light sensor.
 */
class LightLevel extends AbstractResource
{
    use HasEnabled;

    /**
     * Light level in 10000*log10(lux)+1 units, as reported by the bridge.
     */
    public function getLightLevel(): ?int
    {
        if (isset($this->data->light->light_level_report->light_level)) {
            return (int) $this->data->light->light_level_report->light_level;
        }

        return isset($this->data->light->light_level)
            ? (int) $this->data->light->light_level
            : null;
    }

    public function getLastChanged(): ?string
    {
        return isset($this->data->light->light_level_report->changed)
            ? (string) $this->data->light->light_level_report->changed
            : null;
    }
}
