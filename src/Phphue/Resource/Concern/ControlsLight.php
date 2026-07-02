<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource\Concern;

use Phphue\State\Color;
use Phphue\State\LightState;

/**
 * Shared light controls for `light` and `grouped_light` resources.
 *
 * Each setter sends a PUT and returns $this so calls can be chained.
 */
trait ControlsLight
{
    public function isOn(): bool
    {
        return (bool) ($this->data->on->on ?? false);
    }

    public function getBrightness(): ?float
    {
        return isset($this->data->dimming->brightness) ? (float) $this->data->dimming->brightness : null;
    }

    /**
     * @return array{x: float, y: float}|null
     */
    public function getColorXY(): ?array
    {
        if (! isset($this->data->color->xy)) {
            return null;
        }

        return [
            'x' => (float) $this->data->color->xy->x,
            'y' => (float) $this->data->color->xy->y,
        ];
    }

    /**
     * @return array{red: int, green: int, blue: int}|null
     */
    public function getColorRGB(): ?array
    {
        $xy = $this->getColorXY();

        if ($xy === null) {
            return null;
        }

        return Color::xyToRgb($xy['x'], $xy['y'], $this->getBrightness() ?? 100.0);
    }

    public function getColorTemperature(): ?int
    {
        // isset() is false when mirek is null (color temperature not active).
        return isset($this->data->color_temperature->mirek)
            ? (int) $this->data->color_temperature->mirek
            : null;
    }

    /**
     * Apply a fully built state to this resource.
     */
    public function applyState(LightState $state): static
    {
        if (! $state->isEmpty()) {
            $this->update($state->toArray());
        }

        return $this;
    }

    public function setOn(bool $flag = true): static
    {
        return $this->applyState((new LightState())->on($flag));
    }

    public function on(): static
    {
        return $this->setOn(true);
    }

    public function off(): static
    {
        return $this->setOn(false);
    }

    public function setBrightness(float $level): static
    {
        return $this->applyState((new LightState())->brightness($level));
    }

    public function setColorXY(float $x, float $y): static
    {
        return $this->applyState((new LightState())->colorXY($x, $y));
    }

    public function setColorRGB(int $red, int $green, int $blue, bool $applyBrightness = false): static
    {
        return $this->applyState((new LightState())->colorRGB($red, $green, $blue, $applyBrightness));
    }

    public function setColorTemperature(int $mirek): static
    {
        return $this->applyState((new LightState())->colorTemperature($mirek));
    }

    /**
     * Apply a dynamic effect (candle, fire, sparkle, ...).
     *
     * Pass LightState::EFFECT_NO_EFFECT to clear the running effect.
     */
    public function setEffect(string $effect): static
    {
        return $this->applyState((new LightState())->effect($effect));
    }

    /**
     * Apply a dynamic effect through `effects_v2`, optionally tuning its speed (0-1).
     */
    public function setEffectV2(string $effect, ?float $speed = null): static
    {
        return $this->applyState((new LightState())->effectV2($effect, $speed));
    }
}
