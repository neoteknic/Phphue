<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `entertainment` resource - per-device entertainment capabilities.
 */
class Entertainment extends AbstractResource
{
    public function isRenderer(): bool
    {
        return (bool) ($this->data->renderer ?? false);
    }

    public function isProxy(): bool
    {
        return (bool) ($this->data->proxy ?? false);
    }

    /**
     * Maximum number of parallel streaming sessions, when advertised.
     */
    public function getMaxStreams(): ?int
    {
        return isset($this->data->max_streams) ? (int) $this->data->max_streams : null;
    }
}
