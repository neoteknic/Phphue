<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\Command;

use Phphue\Command\GetResources;
use Phphue\Resource\Light;

/**
 * Tests for Phphue\Command\GetResources
 */
class GetResourcesTest extends AbstractCommandCase
{
    public function testRequestsTheTypeEndpoint(): void
    {
        $this->mockTransport->expects($this->once())
            ->method('sendRequest')
            ->with('/clip/v2/resource/light', 'GET')
            ->willReturn([]);

        $result = (new GetResources('light'))->send($this->mockClient);

        $this->assertSame([], $result);
    }

    public function testWrapsDataAsTypedResources(): void
    {
        $this->mockTransport->method('sendRequest')->willReturn([
            $this->resource('light', 'abc'),
            $this->resource('light', 'def'),
        ]);

        $result = (new GetResources('light'))->send($this->mockClient);

        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Light::class, $result);
        $this->assertSame('abc', $result[0]->getId());
    }
}
