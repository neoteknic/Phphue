<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Transport\Exception;

/**
 * Raised on HTTP 503 - the bridge is overloaded / buffer is full.
 */
class BridgeBusyException extends HueException
{
}
