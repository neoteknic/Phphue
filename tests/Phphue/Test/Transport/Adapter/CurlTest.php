<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\Transport\Adapter;

use CurlHandle;
use Phphue\Test\Support\LocalHttpServer;
use Phphue\Transport\Adapter\Curl;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the cURL adapter connection lifecycle.
 */
#[RequiresPhpExtension('curl')]
class CurlTest extends TestCase
{
    public function testOpenCreatesHandle(): void
    {
        $adapter = new Curl(false);
        $adapter->open();

        $this->assertInstanceOf(CurlHandle::class, $adapter->getCurl());
    }

    public function testCloseKeepsHandleAliveForReuse(): void
    {
        $adapter = new Curl(false);
        $adapter->open();
        $handle = $adapter->getCurl();

        $adapter->close();

        // close() must not drop the handle - the connection stays pooled.
        $this->assertSame($handle, $adapter->getCurl());
    }

    public function testSubsequentOpenReusesSameHandle(): void
    {
        $adapter = new Curl(false);

        $adapter->open();
        $first = $adapter->getCurl();

        $adapter->close();
        $adapter->open();

        // A second request reuses the same handle (reset), it is not re-created.
        $this->assertSame($first, $adapter->getCurl());
    }

    public function testDisconnectDropsHandle(): void
    {
        $adapter = new Curl(false);
        $adapter->open();

        $adapter->disconnect();

        $this->assertNull($adapter->getCurl());
    }

    public function testResponseInfoIsEmptyBeforeAnyRequest(): void
    {
        $adapter = new Curl();

        $this->assertSame(0, $adapter->getHttpStatusCode());
        $this->assertNull($adapter->getContentType());

        $adapter->open();
        $adapter->disconnect();
        $this->assertSame(0, $adapter->getHttpStatusCode());
    }

    public function testSendWithoutOpenCreatesHandleOnDemand(): void
    {
        $server = new LocalHttpServer();
        $adapter = new Curl();

        $body = $adapter->send($server->url . '/resource', 'GET');

        $this->assertIsString($body);
        $this->assertSame('GET', json_decode($body)->data[0]->method);
        $this->assertSame(200, $adapter->getHttpStatusCode());
        $this->assertSame('application/json', $adapter->getContentType());
    }

    public function testEmptyAddressIsRejectedBeforeAnyIo(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Curl())->send('', 'GET');
    }

    public function testExposesItsConfiguration(): void
    {
        $adapter = new Curl(true, 7, 'hue-ca.pem', persistent: true);

        $this->assertTrue($adapter->isSslVerify());
        $this->assertSame(7, $adapter->getTimeout());
        $this->assertSame('hue-ca.pem', $adapter->getCaFile());
        $this->assertTrue($adapter->isPersistent());

        $default = new Curl();
        $this->assertFalse($default->isSslVerify());
        $this->assertSame(4, $default->getTimeout());
        $this->assertNull($default->getCaFile());
        $this->assertFalse($default->isPersistent());
    }

    public function testNegativeTimeoutIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Curl(timeout: -1);
    }
}
