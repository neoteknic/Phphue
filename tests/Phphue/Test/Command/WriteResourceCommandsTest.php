<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\Command;

use Phphue\Command\CreateResource;
use Phphue\Command\DeleteResource;
use Phphue\Command\GetAllResources;
use Phphue\Command\UpdateResource;

/**
 * Tests for the create / update / delete / get-all generic commands.
 */
class WriteResourceCommandsTest extends AbstractCommandCase
{
    public function testCreateResourcePostsBody(): void
    {
        $body = ['metadata' => ['name' => 'Office'], 'type' => 'room'];

        $this->mockTransport->expects($this->once())
            ->method('sendRequest')
            ->with('/clip/v2/resource/room', 'POST', $body)
            ->willReturn([$this->resource('room', 'new')]);

        $result = (new CreateResource('room', $body))->send($this->mockClient);

        $this->assertSame('new', $result[0]->id);
    }

    public function testUpdateResourcePutsBody(): void
    {
        $body = ['on' => ['on' => true]];

        $this->mockTransport->expects($this->once())
            ->method('sendRequest')
            ->with('/clip/v2/resource/light/abc', 'PUT', $body)
            ->willReturn([$this->resource('light', 'abc')]);

        (new UpdateResource('light', 'abc', $body))->send($this->mockClient);
    }

    public function testDeleteResourceSendsDelete(): void
    {
        $this->mockTransport->expects($this->once())
            ->method('sendRequest')
            ->with('/clip/v2/resource/scene/abc', 'DELETE')
            ->willReturn([$this->resource('scene', 'abc')]);

        (new DeleteResource('scene', 'abc'))->send($this->mockClient);
    }

    public function testGetAllResourcesHitsRootEndpoint(): void
    {
        $this->mockTransport->expects($this->once())
            ->method('sendRequest')
            ->with('/clip/v2/resource', 'GET')
            ->willReturn([$this->resource('device', 'd1')]);

        $result = (new GetAllResources())->send($this->mockClient);

        $this->assertCount(1, $result);
        $this->assertSame('device', $result[0]->getType());
    }
}
