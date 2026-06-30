<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * Fallback wrapper used for any resource type without a dedicated class.
 *
 * Every attribute remains reachable through {@see AbstractResource::attr()} and
 * {@see AbstractResource::getRaw()}, and the generic update()/delete() helpers work.
 */
class GenericResource extends AbstractResource
{
}
