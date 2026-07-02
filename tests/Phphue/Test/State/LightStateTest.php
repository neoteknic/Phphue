<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\State;

use Phphue\State\LightState;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Phphue\State\LightState body builder.
 */
class LightStateTest extends TestCase
{
    public function testBuildsExpectedBody(): void
    {
        $body = (new LightState())
            ->on()
            ->brightness(80)
            ->colorXY(0.3, 0.4)
            ->transition(400)
            ->toArray();

        $this->assertSame(['on' => true], $body['on']);
        $this->assertSame(['brightness' => 80.0], $body['dimming']);
        $this->assertSame(['xy' => ['x' => 0.3, 'y' => 0.4]], $body['color']);
        $this->assertSame(['duration' => 400], $body['dynamics']);
    }

    public function testColorTemperatureValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LightState())->colorTemperature(1000);
    }

    public function testBrightnessValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LightState())->brightness(150);
    }

    public function testColorRGBSetsXy(): void
    {
        $body = (new LightState())->colorRGB(255, 0, 0)->toArray();

        $this->assertArrayHasKey('color', $body);
        $this->assertArrayHasKey('xy', $body['color']);
        $this->assertGreaterThan(0.6, $body['color']['xy']['x']);
    }

    public function testEffectBuildsBody(): void
    {
        $body = (new LightState())->effect(LightState::EFFECT_SPARKLE)->toArray();

        $this->assertSame(['effect' => 'sparkle'], $body['effects']);
    }

    public function testEffectV2WithSpeed(): void
    {
        $body = (new LightState())->effectV2(LightState::EFFECT_PRISM, 0.6)->toArray();

        $this->assertSame(
            ['action' => ['effect' => 'prism', 'parameters' => ['speed' => 0.6]]],
            $body['effects_v2']
        );
    }

    public function testEffectV2WithoutSpeedOmitsParameters(): void
    {
        $body = (new LightState())->effectV2(LightState::EFFECT_FIRE)->toArray();

        $this->assertSame(['action' => ['effect' => 'fire']], $body['effects_v2']);
    }

    public function testEffectV2SpeedValidation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new LightState())->effectV2(LightState::EFFECT_SPARKLE, 1.5);
    }

    public function testEmptyByDefault(): void
    {
        $this->assertTrue((new LightState())->isEmpty());
    }
}
