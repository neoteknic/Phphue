<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `device_power` resource - battery state of a battery powered device.
 */
class DevicePower extends AbstractResource
{
    public function getBatteryLevel(): ?int
    {
        return isset($this->data->power_state->battery_level)
            ? (int) $this->data->power_state->battery_level
            : null;
    }

    public function getBatteryState(): ?string
    {
        return isset($this->data->power_state->battery_state)
            ? (string) $this->data->power_state->battery_state
            : null;
    }
}
