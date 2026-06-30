<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasEnabled;
use Phphue\Resource\Concern\HasMetadata;

/**
 * `behavior_instance` resource - an instance of a behavior script.
 */
class BehaviorInstance extends AbstractResource
{
    use HasMetadata;
    use HasEnabled;

    public function getScriptId(): ?string
    {
        return isset($this->data->script_id) ? (string) $this->data->script_id : null;
    }

    public function getConfiguration(): ?\stdClass
    {
        return $this->data->configuration ?? null;
    }

    public function getState(): ?\stdClass
    {
        return $this->data->state ?? null;
    }
}
