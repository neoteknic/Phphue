<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Command;

use Phphue\Client;

/**
 * Command interface
 */
interface CommandInterface
{
    /**
     * Send command
     *
     * @return mixed Command result
     */
    public function send(Client $client): mixed;
}
