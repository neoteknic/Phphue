<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\Command;

use Phphue\Client;
use Phphue\Transport\TransportInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Shared setup for command tests: a mocked client wired to a mocked transport.
 */
abstract class AbstractCommandCase extends TestCase
{
    /** @var Client&MockObject */
    protected $mockClient;

    /** @var TransportInterface&MockObject */
    protected $mockTransport;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(Client::class);
        $this->mockTransport = $this->createMock(TransportInterface::class);

        $this->mockClient->method('getTransport')->willReturn($this->mockTransport);
    }

    protected function resource(string $type, string $id): \stdClass
    {
        return (object) ['type' => $type, 'id' => $id];
    }
}
