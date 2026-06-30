<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Resource\Concern\ControlsLight;
use Phphue\Resource\Concern\HasMetadata;

/**
 * `light` resource.
 */
class Light extends AbstractResource
{
    use HasMetadata;
    use ControlsLight;

    /**
     * Minimum dim level supported by this light (percentage), when advertised.
     */
    public function getMinDimLevel(): ?float
    {
        return isset($this->data->dimming->min_dim_level)
            ? (float) $this->data->dimming->min_dim_level
            : null;
    }

    /**
     * Color gamut type ("A", "B", "C" or "other"), when this light supports color.
     */
    public function getGamutType(): ?string
    {
        return isset($this->data->color->gamut_type) ? (string) $this->data->color->gamut_type : null;
    }

    public function supportsColor(): bool
    {
        return isset($this->data->color);
    }

    public function supportsColorTemperature(): bool
    {
        return isset($this->data->color_temperature);
    }

    /**
     * Available dynamic effect values, when the light supports effects.
     *
     * @return string[]
     */
    public function getEffectValues(): array
    {
        $values = $this->data->effects->effect_values ?? [];

        return is_array($values) ? array_map('strval', $values) : [];
    }
}
