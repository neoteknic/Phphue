<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasServices;

/**
 * `bridge_home` resource - the top level group containing every room and zone.
 */
class BridgeHome extends AbstractResource
{
    use HasServices;
}
