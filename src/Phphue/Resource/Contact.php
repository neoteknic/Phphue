<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasEnabled;

/**
 * `contact` resource - contact sensor (door/window).
 */
class Contact extends AbstractResource
{
    use HasEnabled;

    /**
     * Contact state: "contact" or "no_contact".
     */
    public function getState(): ?string
    {
        return isset($this->data->contact_report->state)
            ? (string) $this->data->contact_report->state
            : null;
    }

    public function isContact(): bool
    {
        return $this->getState() === 'contact';
    }
}
