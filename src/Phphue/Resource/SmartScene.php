<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasMetadata;

/**
 * `smart_scene` resource - a time based scene (e.g. natural light).
 */
class SmartScene extends AbstractResource
{
    use HasMetadata;

    public const STATE_ACTIVE = 'active';

    public const STATE_INACTIVE = 'inactive';

    public function getState(): ?string
    {
        return isset($this->data->state) ? (string) $this->data->state : null;
    }

    /**
     * Group (room/zone) this smart scene belongs to ({rid, rtype}).
     */
    public function getGroup(): ?\stdClass
    {
        return $this->data->group ?? null;
    }

    /**
     * Activate the smart scene.
     *
     * @return array<int,\stdClass>
     */
    public function activate(): array
    {
        return $this->update(['recall' => ['action' => self::STATE_ACTIVE]]);
    }

    /**
     * Deactivate the smart scene.
     *
     * @return array<int,\stdClass>
     */
    public function deactivate(): array
    {
        return $this->update(['recall' => ['action' => self::STATE_INACTIVE]]);
    }
}
