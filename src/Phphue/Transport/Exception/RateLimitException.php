<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Transport\Exception;

/**
 * Raised on HTTP 429 - too many requests sent to the bridge.
 */
class RateLimitException extends HueException
{
}
