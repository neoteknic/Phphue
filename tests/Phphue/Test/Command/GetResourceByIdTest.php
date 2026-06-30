<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\Command;

use Phphue\Command\GetResourceById;
use Phphue\Resource\Room;
use Phphue\Transport\Exception\NotFoundException;

/**
 * Tests for Phphue\Command\GetResourceById
 */
class GetResourceByIdTest extends AbstractCommandCase
{
    public function testReturnsTypedResource(): void
    {
        $this->mockTransport->expects($this->once())
            ->method('sendRequest')
            ->with('/clip/v2/resource/room/xyz', 'GET')
            ->willReturn([$this->resource('room', 'xyz')]);

        $result = (new GetResourceById('room', 'xyz'))->send($this->mockClient);

        $this->assertInstanceOf(Room::class, $result);
        $this->assertSame('xyz', $result->getId());
    }

    public function testThrowsWhenEmpty(): void
    {
        $this->mockTransport->method('sendRequest')->willReturn([]);

        $this->expectException(NotFoundException::class);

        (new GetResourceById('room', 'missing'))->send($this->mockClient);
    }
}
