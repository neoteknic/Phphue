<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test;

use Phphue\Client;
use Phphue\Transport\Adapter\AdapterInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for host handling and request URLs built by the client.
 */
class ClientTest extends TestCase
{
    public static function hosts(): array
    {
        return [
            'bare ip' => ['192.168.1.10', 'https://192.168.1.10'],
            'trailing slash' => ['192.168.1.10/', 'https://192.168.1.10'],
            'surrounding spaces' => [' bridge.local ', 'https://bridge.local'],
            'explicit http and port' => ['http://127.0.0.1:8080/', 'http://127.0.0.1:8080'],
            'uppercase scheme' => ['HTTPS://Bridge', 'HTTPS://Bridge'],
        ];
    }

    #[DataProvider('hosts')]
    public function testBaseUrl(string $host, string $expected): void
    {
        $client = new Client($host);

        $this->assertSame($expected, $client->getBaseUrl());
        // The host is still reported exactly as configured.
        $this->assertSame($host, $client->getHost());
    }

    public static function emptyHosts(): array
    {
        return array_map(static fn (string $host): array => [$host], ['', '   ', '/', 'https://', 'http:///']);
    }

    #[DataProvider('emptyHosts')]
    public function testEmptyHostIsRejected(string $host): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client($host);
    }

    public function testChangingHostRedirectsNextRequest(): void
    {
        $urls = [];
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('send')->willReturnCallback(
            static function (string $url) use (&$urls): string {
                $urls[] = $url;

                return '{"errors":[],"data":[]}';
            }
        );
        $adapter->method('getHttpStatusCode')->willReturn(200);

        $client = new Client('10.0.0.1', 'key');
        $client->getTransport()->setAdapter($adapter);

        $client->getResources('light');
        $client->setHost('10.0.0.2/');
        $client->getResources('light');

        $this->assertSame([
            'https://10.0.0.1/clip/v2/resource/light',
            'https://10.0.0.2/clip/v2/resource/light',
        ], $urls);
    }

    public function testInvalidHostDoesNotReplaceCurrentOne(): void
    {
        $client = new Client('10.0.0.1');

        try {
            $client->setHost(' ');
            $this->fail('An empty host must be rejected');
        } catch (\InvalidArgumentException) {
            $this->assertSame('https://10.0.0.1', $client->getBaseUrl());
        }
    }
}
