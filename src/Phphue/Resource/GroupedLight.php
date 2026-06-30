<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\ControlsLight;

/**
 * `grouped_light` resource - controls every light of a room or zone at once.
 */
class GroupedLight extends AbstractResource
{
    use ControlsLight;
}
