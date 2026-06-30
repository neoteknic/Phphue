<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `behavior_script` resource - a behavior template available on the bridge.
 */
class BehaviorScript extends AbstractResource
{
    public function getDescription(): ?string
    {
        return isset($this->data->description) ? (string) $this->data->description : null;
    }

    public function getVersion(): ?string
    {
        return isset($this->data->version) ? (string) $this->data->version : null;
    }

    public function getConfigurationSchema(): ?\stdClass
    {
        return $this->data->configuration_schema ?? null;
    }
}
