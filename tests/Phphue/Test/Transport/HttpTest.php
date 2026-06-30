<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\Transport;

use Phphue\Client;
use Phphue\Transport\Adapter\AdapterInterface;
use Phphue\Transport\Exception\HueException;
use Phphue\Transport\Exception\UnauthorizedException;
use Phphue\Transport\Http;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Phphue\Transport\Http (CLIP v2 envelope handling).
 */
class HttpTest extends TestCase
{
    private Client $client;

    private Http $http;

    protected function setUp(): void
    {
        $this->client = new Client('192.168.1.10', 'app-key');
        $this->http = new Http($this->client);
        $this->client->setTransport($this->http);
    }

    private function adapter(string $body, int $status): AdapterInterface
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('send')->willReturn($body);
        $adapter->method('getHttpStatusCode')->willReturn($status);
        $adapter->method('getContentType')->willReturn('application/json');

        return $adapter;
    }

    public function testSendRequestReturnsData(): void
    {
        $this->http->setAdapter($this->adapter(
            json_encode(['errors' => [], 'data' => [['type' => 'light', 'id' => 'abc']]]),
            200
        ));

        $data = $this->http->sendRequest('/clip/v2/resource/light');

        $this->assertCount(1, $data);
        $this->assertSame('abc', $data[0]->id);
    }

    public function testErrorsArrayRaisesHueException(): void
    {
        $this->http->setAdapter($this->adapter(
            json_encode(['errors' => [['description' => 'device is not on']], 'data' => []]),
            200
        ));

        $this->expectException(HueException::class);
        $this->expectExceptionMessage('device is not on');

        $this->http->sendRequest('/clip/v2/resource/light/abc', Http::METHOD_PUT, ['on' => ['on' => true]]);
    }

    public function testUnauthorizedStatusMapsToException(): void
    {
        $this->http->setAdapter($this->adapter(
            json_encode(['errors' => [['description' => 'requires authentication']]]),
            401
        ));

        $this->expectException(UnauthorizedException::class);

        $this->http->sendRequest('/clip/v2/resource/light');
    }

    public function testSendRawReturnsDecodedBody(): void
    {
        $this->http->setAdapter($this->adapter(
            json_encode([(object) ['success' => (object) ['username' => 'KEY']]]),
            200
        ));

        $raw = $this->http->sendRaw('/api', Http::METHOD_POST, ['devicetype' => 'x#y']);

        $this->assertIsArray($raw);
        $this->assertSame('KEY', $raw[0]->success->username);
    }
}
