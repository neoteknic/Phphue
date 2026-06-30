<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `relative_rotary` resource - rotary dial (e.g. Hue Tap dial).
 */
class RelativeRotary extends AbstractResource
{
    /**
     * Last rotary action ("start" or "repeat"), when reported.
     */
    public function getLastAction(): ?string
    {
        return isset($this->data->relative_rotary->rotary_report->action)
            ? (string) $this->data->relative_rotary->rotary_report->action
            : null;
    }

    /**
     * Rotation report ({direction, steps, duration}), when reported.
     */
    public function getRotation(): ?\stdClass
    {
        return $this->data->relative_rotary->rotary_report->rotation ?? null;
    }
}
