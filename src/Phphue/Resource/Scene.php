<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\HasMetadata;

/**
 * `scene` resource.
 */
class Scene extends AbstractResource
{
    use HasMetadata;

    public const ACTION_ACTIVE = 'active';

    public const ACTION_DYNAMIC_PALETTE = 'dynamic_palette';

    public const ACTION_STATIC = 'static';

    /**
     * Group (room/zone) this scene belongs to ({rid, rtype}).
     */
    public function getGroup(): ?\stdClass
    {
        return $this->data->group ?? null;
    }

    public function getStatus(): ?string
    {
        return isset($this->data->status->active) ? (string) $this->data->status->active : null;
    }

    /**
     * Recall (activate) the scene.
     *
     * @param string $action active | dynamic_palette | static
     * @param int|null $durationMs Optional transition duration in milliseconds
     * @return array<int,\stdClass>
     */
    public function recall(string $action = self::ACTION_ACTIVE, ?int $durationMs = null): array
    {
        $recall = ['action' => $action];

        if ($durationMs !== null) {
            $recall['duration'] = $durationMs;
        }

        return $this->update(['recall' => $recall]);
    }
}
