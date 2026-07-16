<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\Transport\Adapter;

use CurlHandle;
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
}
