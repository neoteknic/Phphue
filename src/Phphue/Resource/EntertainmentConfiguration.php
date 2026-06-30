<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasMetadata;

/**
 * `entertainment_configuration` resource.
 *
 * Only the REST control of the configuration is exposed here; the real-time
 * DTLS streaming is out of scope for now.
 */
class EntertainmentConfiguration extends AbstractResource
{
    use HasMetadata;

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_INACTIVE = 'inactive';

    public function getStatus(): ?string
    {
        return isset($this->data->status) ? (string) $this->data->status : null;
    }

    public function getConfigurationType(): ?string
    {
        return isset($this->data->configuration_type) ? (string) $this->data->configuration_type : null;
    }

    public function isActive(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE;
    }

    /**
     * Channels of this configuration ({channel_id, position, members}).
     *
     * @return array<int,\stdClass>
     */
    public function getChannels(): array
    {
        $channels = $this->data->channels ?? [];

        return is_array($channels) ? $channels : [];
    }

    /**
     * Start streaming mode (required before opening a DTLS stream).
     *
     * @return array<int,\stdClass>
     */
    public function start(): array
    {
        return $this->update(['action' => 'start']);
    }

    /**
     * Stop streaming mode.
     *
     * @return array<int,\stdClass>
     */
    public function stop(): array
    {
        return $this->update(['action' => 'stop']);
    }
}
