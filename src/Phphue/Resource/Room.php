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
 * `room` resource - a group of devices located in the same physical room.
 */
class Room extends AbstractResource
{
    use HasMetadata;
    use HasServices;
}
