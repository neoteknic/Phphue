<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\Command;

use Phphue\Command\CreateApplicationKey;
use Phphue\Transport\Exception\LinkButtonNotPressedException;

/**
 * Tests for Phphue\Command\CreateApplicationKey
 */
class CreateApplicationKeyTest extends AbstractCommandCase
{
    public function testReturnsSuccessPayload(): void
    {
        $this->mockTransport->expects($this->once())
            ->method('sendRaw')
            ->with('/api', 'POST', ['devicetype' => 'myapp#php', 'generateclientkey' => true])
            ->willReturn([(object) ['success' => (object) ['username' => 'KEY123', 'clientkey' => 'ABCDEF']]]);

        $result = (new CreateApplicationKey('myapp', 'php', true))->send($this->mockClient);

        $this->assertSame('KEY123', $result->username);
        $this->assertSame('ABCDEF', $result->clientkey);
    }

    public function testThrowsWhenLinkButtonNotPressed(): void
    {
        $this->mockTransport->method('sendRaw')->willReturn([
            (object) ['error' => (object) ['type' => 101, 'description' => 'link button not pressed']],
        ]);

        $this->expectException(LinkButtonNotPressedException::class);

        (new CreateApplicationKey('myapp'))->send($this->mockClient);
    }
}
