<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasMetadata;
use Phphue\Resource\Concern\HasServices;

/**
 * `device` resource - a physical device (bulb, sensor, switch, plug, ...).
 */
class Device extends AbstractResource
{
    use HasMetadata;
    use HasServices;

    /**
     * Product data block (manufacturer, model id, product name, software version).
     */
    public function getProductData(): ?\stdClass
    {
        return $this->data->product_data ?? null;
    }

    public function getModelId(): ?string
    {
        return isset($this->data->product_data->model_id)
            ? (string) $this->data->product_data->model_id
            : null;
    }

    public function getProductName(): ?string
    {
        return isset($this->data->product_data->product_name)
            ? (string) $this->data->product_data->product_name
            : null;
    }

    public function getSoftwareVersion(): ?string
    {
        return isset($this->data->product_data->software_version)
            ? (string) $this->data->product_data->software_version
            : null;
    }

    /**
     * Trigger an identify action (the device blinks) when supported.
     *
     * @return array<int,\stdClass>
     */
    public function identify(): array
    {
        return $this->update(['identify' => ['action' => 'identify']]);
    }
}
