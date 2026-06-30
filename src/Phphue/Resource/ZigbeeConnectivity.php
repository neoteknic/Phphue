<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `zigbee_connectivity` resource - zigbee link status of a device.
 */
class ZigbeeConnectivity extends AbstractResource
{
    /**
     * Connectivity status: connected, disconnected, connectivity_issue, unidirectional_incoming.
     */
    public function getStatus(): ?string
    {
        return isset($this->data->status) ? (string) $this->data->status : null;
    }

    public function getMacAddress(): ?string
    {
        return isset($this->data->mac_address) ? (string) $this->data->mac_address : null;
    }

    public function isConnected(): bool
    {
        return $this->getStatus() === 'connected';
    }
}
