<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasEnabled;

/**
 * `motion` resource - motion sensor.
 */
class Motion extends AbstractResource
{
    use HasEnabled;

    public function isMotionDetected(): bool
    {
        if (isset($this->data->motion->motion_report->motion)) {
            return (bool) $this->data->motion->motion_report->motion;
        }

        return (bool) ($this->data->motion->motion ?? false);
    }

    /**
     * Timestamp of the last motion report (ISO 8601), when available.
     */
    public function getLastChanged(): ?string
    {
        return isset($this->data->motion->motion_report->changed)
            ? (string) $this->data->motion->motion_report->changed
            : null;
    }
}
