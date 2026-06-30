<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasEnabled;

/**
 * `temperature` resource - temperature sensor (degrees Celsius).
 */
class Temperature extends AbstractResource
{
    use HasEnabled;

    public function getCelsius(): ?float
    {
        if (isset($this->data->temperature->temperature_report->temperature)) {
            return (float) $this->data->temperature->temperature_report->temperature;
        }

        return isset($this->data->temperature->temperature)
            ? (float) $this->data->temperature->temperature
            : null;
    }

    public function getLastChanged(): ?string
    {
        return isset($this->data->temperature->temperature_report->changed)
            ? (string) $this->data->temperature->temperature_report->changed
            : null;
    }
}
