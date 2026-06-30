<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\State;

/**
 * Fluent builder for the body of a PUT on a `light` (or `grouped_light`) resource.
 *
 * Example:
 *   $state = (new LightState())->on()->brightness(80)->colorRGB(255, 120, 0)->transition(400);
 *   $light->update($state->toArray());
 */
class LightState
{
    public const BRIGHTNESS_MIN = 0.0;

    public const BRIGHTNESS_MAX = 100.0;

    public const MIREK_MIN = 153;

    public const MIREK_MAX = 500;

    public const XY_MIN = 0.0;

    public const XY_MAX = 1.0;

    public const ALERT_BREATHE = 'breathe';

    public const EFFECT_NO_EFFECT = 'no_effect';

    public const EFFECT_CANDLE = 'candle';

    public const EFFECT_FIRE = 'fire';

    public const EFFECT_SPARKLE = 'sparkle';

    public const EFFECT_GLISTEN = 'glisten';

    public const EFFECT_OPAL = 'opal';

    public const EFFECT_PRISM = 'prism';

    /**
     * @var array<string,mixed>
     */
    protected array $params = [];

    public function on(bool $flag = true): static
    {
        $this->params['on'] = ['on' => $flag];

        return $this;
    }

    public function off(): static
    {
        return $this->on(false);
    }

    /**
     * Set brightness as a percentage (0-100).
     *
     * @throws \InvalidArgumentException
     */
    public function brightness(float $level): static
    {
        if (! (self::BRIGHTNESS_MIN <= $level && $level <= self::BRIGHTNESS_MAX)) {
            throw new \InvalidArgumentException(
                'Brightness must be between ' . self::BRIGHTNESS_MIN . ' and ' . self::BRIGHTNESS_MAX
            );
        }

        $this->params['dimming'] = ['brightness' => $level];

        return $this;
    }

    /**
     * Set color from CIE xy coordinates.
     *
     * @throws \InvalidArgumentException
     */
    public function colorXY(float $x, float $y): static
    {
        foreach ([$x, $y] as $value) {
            if (! (self::XY_MIN <= $value && $value <= self::XY_MAX)) {
                throw new \InvalidArgumentException(
                    'x/y value must be between ' . self::XY_MIN . ' and ' . self::XY_MAX
                );
            }
        }

        $this->params['color'] = ['xy' => ['x' => $x, 'y' => $y]];

        return $this;
    }

    /**
     * Set color from 8-bit RGB. Optionally also sets brightness from the RGB luminance.
     */
    public function colorRGB(int $red, int $green, int $blue, bool $applyBrightness = false): static
    {
        foreach ([$red, $green, $blue] as $value) {
            if (! (0 <= $value && $value <= 255)) {
                throw new \InvalidArgumentException('RGB values must be between 0 and 255');
            }
        }

        $xy = Color::rgbToXy($red, $green, $blue);
        $this->colorXY($xy['x'], $xy['y']);

        if ($applyBrightness) {
            $this->brightness($xy['brightness']);
        }

        return $this;
    }

    /**
     * Set color temperature on the mired/mirek scale (153-500).
     *
     * @throws \InvalidArgumentException
     */
    public function colorTemperature(int $mirek): static
    {
        if (! (self::MIREK_MIN <= $mirek && $mirek <= self::MIREK_MAX)) {
            throw new \InvalidArgumentException(
                'Color temperature (mirek) must be between ' . self::MIREK_MIN . ' and ' . self::MIREK_MAX
            );
        }

        $this->params['color_temperature'] = ['mirek' => $mirek];

        return $this;
    }

    /**
     * Set color temperature from a value in Kelvin.
     */
    public function colorTemperatureKelvin(int $kelvin): static
    {
        return $this->colorTemperature(Color::kelvinToMirek($kelvin));
    }

    /**
     * Transition duration in milliseconds.
     *
     * @throws \InvalidArgumentException
     */
    public function transition(int $milliseconds): static
    {
        if ($milliseconds < 0) {
            throw new \InvalidArgumentException('Transition duration must be at least 0');
        }

        $dynamics = $this->params['dynamics'] ?? [];
        $dynamics['duration'] = $milliseconds;
        $this->params['dynamics'] = $dynamics;

        return $this;
    }

    /**
     * Dynamic palette speed (0-1), only meaningful for scenes/dynamic scenes.
     */
    public function dynamicsSpeed(float $speed): static
    {
        if (! (0.0 <= $speed && $speed <= 1.0)) {
            throw new \InvalidArgumentException('Dynamics speed must be between 0 and 1');
        }

        $dynamics = $this->params['dynamics'] ?? [];
        $dynamics['speed'] = $speed;
        $this->params['dynamics'] = $dynamics;

        return $this;
    }

    /**
     * Trigger an alert effect (breathe).
     */
    public function alert(string $action = self::ALERT_BREATHE): static
    {
        $this->params['alert'] = ['action' => $action];

        return $this;
    }

    /**
     * Apply a light effect (candle, fire, sparkle, ...).
     */
    public function effect(string $effect): static
    {
        $this->params['effects'] = ['effect' => $effect];

        return $this;
    }

    /**
     * Set arbitrary raw parameters, merged into the body (escape hatch).
     *
     * @param array<string,mixed> $params
     */
    public function raw(array $params): static
    {
        $this->params = array_replace($this->params, $params);

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return $this->params;
    }

    public function isEmpty(): bool
    {
        return $this->params === [];
    }
}
