<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\State;

/**
 * Helper for converting colors to and from the CIE xy color space used by Hue.
 *
 * Ported from the v1 Phue \Phue\Helper\ColorConversion, adapted to the CLIP v2
 * model where brightness is expressed as a percentage (0-100).
 */
class Color
{
    /**
     * Convert 8-bit RGB to CIE xy + brightness percentage.
     *
     * Based on: http://stackoverflow.com/a/22649803
     *
     * @return array{x: float, y: float, brightness: float}
     */
    public static function rgbToXy(int $red, int $green, int $blue): array
    {
        $normalizedToOne = [
            'red' => $red / 255,
            'green' => $green / 255,
            'blue' => $blue / 255,
        ];

        $color = [];
        foreach ($normalizedToOne as $key => $normalized) {
            if ($normalized > 0.04045) {
                $color[$key] = (($normalized + 0.055) / (1.0 + 0.055)) ** 2.4;
            } else {
                $color[$key] = $normalized / 12.92;
            }
        }

        $xyz = [
            'x' => $color['red'] * 0.664511 + $color['green'] * 0.154324 + $color['blue'] * 0.162028,
            'y' => $color['red'] * 0.283881 + $color['green'] * 0.668433 + $color['blue'] * 0.047685,
            'z' => $color['red'] * 0.000000 + $color['green'] * 0.072310 + $color['blue'] * 0.986039,
        ];
        $xyzSum = array_sum($xyz);

        if ($xyzSum == 0.0) {
            $x = 0.0;
            $y = 0.0;
        } else {
            $x = $xyz['x'] / $xyzSum;
            $y = $xyz['y'] / $xyzSum;
        }

        // CLIP v2 brightness is a percentage of the maximum.
        $brightness = max(0.0, min(100.0, $xyz['y'] * 100));

        return [
            'x' => $x,
            'y' => $y,
            'brightness' => $brightness,
        ];
    }

    /**
     * Convert CIE xy (+ brightness percentage) back to 8-bit RGB.
     *
     * @return array{red: int, green: int, blue: int}
     */
    public static function xyToRgb(float $x, float $y, float $brightness = 100.0): array
    {
        if ($y == 0.0) {
            $y = 0.0001;
        }

        $z = 1.0 - $x - $y;
        $xyz = [];
        $xyz['y'] = max(0.0, min(1.0, $brightness / 100));
        $xyz['x'] = ($xyz['y'] / $y) * $x;
        $xyz['z'] = ($xyz['y'] / $y) * $z;

        $color = [
            'red' => $xyz['x'] * 1.656492 - $xyz['y'] * 0.354851 - $xyz['z'] * 0.255038,
            'green' => -$xyz['x'] * 0.707196 + $xyz['y'] * 1.655397 + $xyz['z'] * 0.036152,
            'blue' => $xyz['x'] * 0.051713 - $xyz['y'] * 0.121364 + $xyz['z'] * 1.011530,
        ];

        $maxValue = 0.0;
        foreach ($color as $key => $normalized) {
            if ($normalized <= 0.0031308) {
                $color[$key] = 12.92 * $normalized;
            } else {
                $color[$key] = (1.0 + 0.055) * ($normalized ** (1.0 / 2.4)) - 0.055;
            }
            $color[$key] = max(0.0, $color[$key]);
            if ($maxValue < $color[$key]) {
                $maxValue = $color[$key];
            }
        }

        $rgb = [];
        foreach ($color as $key => $normalized) {
            if ($maxValue > 1) {
                $normalized /= $maxValue;
            }
            $rgb[$key] = (int) round($normalized * 255);
        }

        return $rgb;
    }

    /**
     * Convert a color temperature in Kelvin to the mired/mirek scale used by Hue.
     */
    public static function kelvinToMirek(int $kelvin): int
    {
        if ($kelvin <= 0) {
            throw new \InvalidArgumentException('Kelvin must be greater than 0');
        }

        return (int) round(1000000 / $kelvin);
    }

    /**
     * Convert a mired/mirek value to a color temperature in Kelvin.
     */
    public static function mirekToKelvin(int $mirek): int
    {
        if ($mirek <= 0) {
            throw new \InvalidArgumentException('Mirek must be greater than 0');
        }

        return (int) round(1000000 / $mirek);
    }
}
