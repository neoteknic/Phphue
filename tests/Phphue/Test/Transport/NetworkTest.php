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

    public function testLargeBodyIsSentWithoutExpectContinueStall(): void
    {
        $server = new LocalHttpServer();
        $client = new Client($server->url, 'app-key');
        $started = microtime(true);
        // libcurl announces bodies above its threshold (1 MB on recent versions,
        // 1 KB on older ones) with "Expect: 100-continue" and then waits up to 1 s.
        $echo = $client->getTransport()->sendRequest('/resource', 'PUT', ['blob' => str_repeat('a', 1_100_000)])[0];
        $elapsed = microtime(true) - $started;

        $headers = (array) $echo->headers;
        $this->assertArrayNotHasKey('expect', $headers);
        $this->assertSame('application/json', $headers['content-type']);
        $this->assertSame('application/json', $headers['accept']);
        $this->assertSame('app-key', $headers['hue-application-key']);
        $this->assertSame(1_100_000 + strlen('{"blob":""}'), strlen($echo->body));
        $this->assertLessThan(0.9, $elapsed);
    }

    public function testBodylessRequestsSendNoContentType(): void
    {
        $server = new LocalHttpServer();
        $echo = (new Client($server->url))->getTransport()->sendRequest('/resource', 'DELETE')[0];

        $headers = (array) $echo->headers;
        $this->assertSame('DELETE', $echo->method);
        $this->assertArrayNotHasKey('content-type', $headers);
        $this->assertArrayNotHasKey('hue-application-key', $headers);
    }

    public function testPersistentAdaptersReuseTheConnectionAcrossClients(): void
    {
        $server = new LocalHttpServer();

        // First "web request": its client and adapter are gone once it completes.
        $first = new Client($server->url);
        $first->getTransport()->setAdapter(new Curl(persistent: true));
        $before = $first->getTransport()->sendRequest('/resource')[0]->connection;
        unset($first);
        gc_collect_cycles(); // Client and Http reference each other.

        $second = new Client($server->url);
        $second->getTransport()->setAdapter(new Curl(persistent: true));
        $after = $second->getTransport()->sendRequest('/resource')[0]->connection;

        $this->assertSame($before, $after);
    }

    public function testNonPersistentAdaptersOpenTheirOwnConnection(): void
    {
        $server = new LocalHttpServer();

        $first = new Client($server->url);
        $before = $first->getTransport()->sendRequest('/resource')[0]->connection;
        unset($first);
        gc_collect_cycles(); // Client and Http reference each other.

        $after = (new Client($server->url))->getTransport()->sendRequest('/resource')[0]->connection;

        $this->assertNotSame($before, $after);
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
