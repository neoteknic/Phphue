<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `matter` resource - Matter fabric/commissioning status of the bridge.
 */
class Matter extends AbstractResource
{
    public function getMaxFabrics(): ?int
    {
        return isset($this->data->max_fabrics) ? (int) $this->data->max_fabrics : null;
    }

    public function hasQrCode(): bool
    {
        return (bool) ($this->data->has_qr_code ?? false);
    }
}
