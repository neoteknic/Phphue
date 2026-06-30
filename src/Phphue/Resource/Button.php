<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `button` resource - a single button of a switch device.
 */
class Button extends AbstractResource
{
    /**
     * Physical control id of this button within its device.
     */
    public function getControlId(): ?int
    {
        return isset($this->data->metadata->control_id)
            ? (int) $this->data->metadata->control_id
            : null;
    }

    /**
     * Last button event (initial_press, repeat, short_release, long_press, long_release).
     */
    public function getLastEvent(): ?string
    {
        if (isset($this->data->button->button_report->event)) {
            return (string) $this->data->button->button_report->event;
        }

        return isset($this->data->button->last_event) ? (string) $this->data->button->last_event : null;
    }
}
