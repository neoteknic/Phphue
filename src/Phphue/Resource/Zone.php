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
 * `zone` resource - an arbitrary group of lights (not bound to a physical room).
 */
class Zone extends AbstractResource
{
    use HasMetadata;
    use HasServices;
}
