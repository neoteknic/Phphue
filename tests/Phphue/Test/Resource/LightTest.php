<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\Resource;

use Phphue\Client;
use Phphue\Resource\Light;
use Phphue\State\LightState;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Phphue\Resource\Light accessors and controls.
 */
class LightTest extends TestCase
{
    private function lightData(): \stdClass
    {
        return (object) [
            'type' => 'light',
            'id' => 'light-1',
            'metadata' => (object) ['name' => 'Desk', 'archetype' => 'desk_lamp'],
            'on' => (object) ['on' => true],
            'dimming' => (object) ['brightness' => 42.0],
            'color' => (object) ['xy' => (object) ['x' => 0.5, 'y' => 0.4], 'gamut_type' => 'C'],
            'color_temperature' => (object) ['mirek' => 300],
        ];
    }

    public function testAccessors(): void
    {
        $light = new Light($this->createMock(Client::class), $this->lightData());

        $this->assertSame('light-1', $light->getId());
        $this->assertSame('Desk', $light->getName());
        $this->assertSame('desk_lamp', $light->getArchetype());
        $this->assertTrue($light->isOn());
        $this->assertSame(42.0, $light->getBrightness());
        $this->assertSame(['x' => 0.5, 'y' => 0.4], $light->getColorXY());
        $this->assertSame(300, $light->getColorTemperature());
        $this->assertTrue($light->supportsColor());
        $this->assertSame('C', $light->getGamutType());
    }

    public function testSetOnSendsUpdate(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('updateResource')
            ->with('light', 'light-1', ['on' => ['on' => false]])
            ->willReturn([]);

        $light = new Light($client, $this->lightData());
        $this->assertSame($light, $light->off());
    }

    public function testSetBrightnessSendsUpdate(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('updateResource')
            ->with('light', 'light-1', ['dimming' => ['brightness' => 75.0]])
            ->willReturn([]);

        $light = new Light($client, $this->lightData());
        $light->setBrightness(75.0);
    }

    public function testSetEffectSendsUpdate(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('updateResource')
            ->with('light', 'light-1', ['effects' => ['effect' => 'sparkle']])
            ->willReturn([]);

        $light = new Light($client, $this->lightData());
        $this->assertSame($light, $light->setEffect(LightState::EFFECT_SPARKLE));
    }

    public function testSetEffectV2SendsUpdateWithSpeed(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('updateResource')
            ->with('light', 'light-1', ['effects_v2' => ['action' => ['effect' => 'prism', 'parameters' => ['speed' => 0.5]]]])
            ->willReturn([]);

        $light = new Light($client, $this->lightData());
        $light->setEffectV2(LightState::EFFECT_PRISM, 0.5);
    }

    public function testGetEffectValues(): void
    {
        $data = $this->lightData();
        $data->effects = (object) ['effect_values' => ['no_effect', 'candle', 'sparkle']];

        $light = new Light($this->createMock(Client::class), $data);

        $this->assertSame(['no_effect', 'candle', 'sparkle'], $light->getEffectValues());
    }
}
