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
use Phphue\Transport\Exception\ConnectionException;
use Phphue\Transport\Exception\UnauthorizedException;
use Phphue\Transport\Http;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

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

    public function testWarningsAreNonBlockingByDefault(): void
    {
        $this->http->setAdapter($this->adapter(
            json_encode([
                'errors' => [['description' => 'device has communication issues, command may not have effect']],
                'data' => [['rid' => 'abc', 'rtype' => 'light']],
            ]),
            200
        ));

        $data = $this->http->sendRequest('/clip/v2/resource/light/abc', Http::METHOD_PUT, ['on' => ['on' => true]]);

        // The bridge accepted the command (2xx + data), so it must not throw.
        $this->assertCount(1, $data);
        $this->assertSame(
            ['device has communication issues, command may not have effect'],
            $this->http->getLastWarnings()
        );
    }

    public function testWarningHandlerReceivesDescriptions(): void
    {
        $this->http->setAdapter($this->adapter(
            json_encode(['errors' => [['description' => 'may not have effect']], 'data' => [(object) ['rid' => 'abc', 'rtype' => 'light']]]),
            200
        ));

        $captured = [];
        $this->http->setWarningHandler(static function (array $warnings) use (&$captured): void {
            $captured = $warnings;
        });

        $this->http->sendRequest('/clip/v2/resource/light/abc', Http::METHOD_PUT, ['on' => ['on' => true]]);

        $this->assertSame(['may not have effect'], $captured);
    }

    public function testThrowOnWarningsRestoresStrictBehaviour(): void
    {
        $this->http->setAdapter($this->adapter(
            json_encode(['errors' => [['description' => 'device is not on']], 'data' => [(object) ['rid' => 'abc', 'rtype' => 'light']]]),
            200
        ));

        $this->http->setThrowOnWarnings(true);

        $this->expectException(HueException::class);
        $this->expectExceptionMessage('device is not on');

        $this->http->sendRequest('/clip/v2/resource/light/abc', Http::METHOD_PUT, ['on' => ['on' => true]]);
    }

    public function testLastWarningsResetBetweenRequests(): void
    {
        $this->http->setAdapter($this->adapter(
            json_encode(['errors' => [['description' => 'transient warning']], 'data' => [(object) ['id' => 'abc', 'type' => 'light']]]),
            200
        ));
        $this->http->sendRequest('/clip/v2/resource/light');
        $this->assertSame(['transient warning'], $this->http->getLastWarnings());

        // A subsequent clean request must clear the previous warnings.
        $this->http->setAdapter($this->adapter(
            json_encode(['errors' => [], 'data' => [['type' => 'light', 'id' => 'abc']]]),
            200
        ));
        $this->http->sendRequest('/clip/v2/resource/light');
        $this->assertSame([], $this->http->getLastWarnings());
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

    public static function invalidResponses(): array
    {
        return array_map(static fn (string $body): array => [$body], [
            '{}', 'null', '[]', '{broken', '{"errors":[],"data":null}',
            '{"errors":{},"data":[]}', '{"errors":[],"data":[42]}',
            '{"errors":["bad"],"data":[]}',
        ]);
    }

    #[DataProvider('invalidResponses')]
    public function testRejectsInvalidResponse(string $body): void
    {
        $this->http->setAdapter($this->adapter($body, 200));
        $this->expectException(ConnectionException::class);
        $this->http->sendRequest('/clip/v2/resource/light');
    }

    public function testEmptyCollectionIsValid(): void
    {
        $this->http->setAdapter($this->adapter('{"errors":[],"data":[]}', 200));
        $this->assertSame([], $this->http->sendRequest('/clip/v2/resource/light'));
    }

    public function testErrorsWithoutDataThrowAndRemainInspectable(): void
    {
        $this->http->setAdapter($this->adapter('{"errors":[{"description":"rejected"}],"data":[]}', 200));
        try {
            $this->http->sendRequest('/clip/v2/resource/light/abc', Http::METHOD_PUT, ['on' => ['on' => true]]);
            $this->fail('An unsuccessful command must throw');
        } catch (HueException $e) {
            $this->assertSame('rejected', $e->getMessage());
            $this->assertSame(['rejected'], $this->http->getLastWarnings());
        }
    }

    public function testInvalidPayloadNeverOpensConnection(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->expects($this->never())->method('open');
        $adapter->expects($this->never())->method('send');
        $this->http->setAdapter($adapter);
        $this->expectException(\JsonException::class);
        $this->http->sendRequest('/clip/v2/resource/light/abc', Http::METHOD_PUT, ['name' => "\xB1\x31"]);
    }

    public function testAdapterIsClosedOnFailure(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('send')->willThrowException(new ConnectionException('timeout'));
        $adapter->expects($this->once())->method('close');
        $this->http->setAdapter($adapter);
        $this->expectException(ConnectionException::class);
        $this->http->sendRequest('/clip/v2/resource/light');
    }

    public function testFailedTransferWithHttp200StillThrows(): void
    {
        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('send')->willReturn(false);
        $adapter->method('getHttpStatusCode')->willReturn(200);
        $this->http->setAdapter($adapter);
        $this->expectException(ConnectionException::class);
        $this->http->sendRequest('/clip/v2/resource/light');
    }

    public function testRawRequestAlsoChecksHttpStatus(): void
    {
        $this->http->setAdapter($this->adapter('not json', 401));
        $this->expectException(UnauthorizedException::class);
        $this->http->sendRaw('/api', Http::METHOD_POST, ['devicetype' => 'test']);
    }
}
