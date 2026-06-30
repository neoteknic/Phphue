<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\State;

use Phphue\State\Color;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Phphue\State\Color conversions.
 */
class ColorTest extends TestCase
{
    public function testRgbToXyReturnsExpectedKeys(): void
    {
        $xy = Color::rgbToXy(255, 0, 0);

        $this->assertArrayHasKey('x', $xy);
        $this->assertArrayHasKey('y', $xy);
        $this->assertArrayHasKey('brightness', $xy);
        $this->assertGreaterThan(0.6, $xy['x']);
        $this->assertLessThanOrEqual(100.0, $xy['brightness']);
    }

    public function testRoundTripIsStable(): void
    {
        $xy = Color::rgbToXy(120, 200, 50);
        $rgb = Color::xyToRgb($xy['x'], $xy['y'], 100.0);

        // Hue colour space is lossy, so check the dominant channel ordering holds.
        $this->assertGreaterThan($rgb['blue'], $rgb['green']);
        $this->assertGreaterThan($rgb['blue'], $rgb['red']);
    }

    public function testKelvinMirekConversion(): void
    {
        $this->assertSame(250, Color::kelvinToMirek(4000));
        $this->assertSame(4000, Color::mirekToKelvin(250));
    }
}
