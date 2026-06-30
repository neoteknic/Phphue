<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Transport\Exception;

/**
 * Raised on HTTP 401 - missing or invalid hue-application-key.
 */
class UnauthorizedException extends HueException
{
}
