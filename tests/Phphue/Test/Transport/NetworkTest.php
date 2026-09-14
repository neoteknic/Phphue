<?php
namespace Phphue\Test\Transport;

use Phphue\Client;
use Phphue\Test\Support\LocalHttpServer;
use Phphue\Transport\Adapter\Curl;
use Phphue\Transport\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('curl')]
class NetworkTest extends TestCase
{
    public function testPutThenGetReusesConnectionWithoutLeakingBody(): void
    {
        $server = new LocalHttpServer();
        $client = new Client($server->url);
        $transport = $client->getTransport();
        $put = $transport->sendRequest('/resource', 'PUT', ['on' => ['on' => true]])[0];
        $get = $transport->sendRequest('/resource')[0];
        $this->assertSame('PUT', $put->method);
        $this->assertSame('{"on":{"on":true}}', $put->body);
        $this->assertSame('GET', $get->method);
        $this->assertSame('', $get->body);
        $this->assertSame($put->connection, $get->connection);
    }

    public function testDefaultTimeoutIsFourSecondsAndIncludesCurlError(): void
    {
        $server = new LocalHttpServer();
        $client = new Client($server->url);
        $started = microtime(true);
        try {
            $client->getTransport()->sendRequest('/stall');
            $this->fail('The stalled response should time out');
        } catch (ConnectionException $e) {
            $elapsed = microtime(true) - $started;
            $this->assertSame(CURLE_OPERATION_TIMEDOUT, $e->getCode());
            $this->assertStringContainsString('curl errno 28', $e->getMessage());
            $this->assertGreaterThanOrEqual(3.5, $elapsed);
            $this->assertLessThan(5.5, $elapsed);
        }
    }

    public function testChangingClientTlsSettingUpdatesExistingAdapterAndDropsConnection(): void
    {
        $client = new Client('unused');
        $adapter = new Curl(false, 4, 'custom-ca.pem');
        $client->getTransport()->setAdapter($adapter);
        $adapter->open();
        $client->setSslVerify(true);
        $this->assertTrue($client->getSslVerify());
        $this->assertSame($adapter, $client->getTransport()->getAdapter());
        $this->assertNull($adapter->getCurl());
        $this->assertTrue((new \ReflectionProperty($adapter, 'sslVerify'))->getValue($adapter));
        $this->assertSame('custom-ca.pem', (new \ReflectionProperty($adapter, 'caFile'))->getValue($adapter));
    }

    public function testTlsSetterAlsoAppliesWhenAdapterPolicyDiffersFromClient(): void
    {
        $client = new Client('unused', sslVerify: false);
        $adapter = new Curl(true);
        $client->getTransport()->setAdapter($adapter);
        $client->setSslVerify(false);
        $this->assertFalse((new \ReflectionProperty($adapter, 'sslVerify'))->getValue($adapter));
    }
}
