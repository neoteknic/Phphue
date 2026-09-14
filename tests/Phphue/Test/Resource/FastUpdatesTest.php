<?php
namespace Phphue\Test\Resource;

use Phphue\Client;
use Phphue\Resource\Light;
use Phphue\Resource\Room;
use Phphue\State\LightState;
use Phphue\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class FastUpdatesTest extends TestCase
{
    private function clientExpectingPut(string $path, array $body): Client
    {
        $transport = $this->createMock(TransportInterface::class);
        // Any extra GET or PUT fails this expectation.
        $transport->expects($this->once())->method('sendRequest')
            ->with($path, TransportInterface::METHOD_PUT, $body)
            ->willReturn([(object) ['rid' => 'updated']]);

        return (new Client('unused'))->setTransport($transport);
    }

    public function testLightUpdateByIdIsOnePutWithoutTransitionOverride(): void
    {
        $state = (new LightState())->on()->brightness(80)->colorXY(0.3, 0.4);
        $client = $this->clientExpectingPut('/clip/v2/resource/light/light-1', $state->toArray());
        $this->assertCount(1, $client->setLightState('light-1', $state));
        $this->assertArrayNotHasKey('dynamics', $state->toArray());
    }

    public function testGroupUpdateByIdIsOnePut(): void
    {
        $state = (new LightState())->off();
        $client = $this->clientExpectingPut('/clip/v2/resource/grouped_light/group-1', $state->toArray());
        $this->assertCount(1, $client->setGroupedLightState('group-1', $state));
    }

    public function testRoomUsesServiceIdWithoutLoadingGroup(): void
    {
        $state = (new LightState())->on()->brightness(80);
        $client = $this->clientExpectingPut('/clip/v2/resource/grouped_light/group-1', $state->toArray());
        $room = new Room($client, (object) [
            'id' => 'room-1', 'type' => 'room',
            'services' => [(object) ['rid' => 'group-1', 'rtype' => 'grouped_light']],
        ]);
        $this->assertSame($room, $room->applyState($state));
    }

    public function testRoomWithoutServiceThrowsBeforeSending(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())->method('sendRequest');
        $room = new Room((new Client('unused'))->setTransport($transport), (object) ['id' => 'room-1']);
        $this->expectException(\LogicException::class);
        $room->applyState((new LightState())->on());
    }

    public function testCallbackBatchesChangesIntoOnePut(): void
    {
        $client = $this->clientExpectingPut('/clip/v2/resource/light/light-1', [
            'on' => ['on' => true], 'dimming' => ['brightness' => 80.0],
        ]);
        $light = new Light($client, (object) ['type' => 'light', 'id' => 'light-1']);
        $this->assertSame($light, $light->updateState(fn (LightState $state) => $state->on()->brightness(80)));
    }

    public function testCallbackFailureDoesNotSendPartialState(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())->method('sendRequest');
        $light = new Light((new Client('unused'))->setTransport($transport), (object) ['type' => 'light', 'id' => 'light-1']);
        $this->expectException(\InvalidArgumentException::class);
        $light->updateState(fn (LightState $state) => $state->on()->brightness(101));
    }

    public function testEmptyStatesDoNotSend(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->never())->method('sendRequest');
        $client = (new Client('unused'))->setTransport($transport);
        $this->assertSame([], $client->setLightState('light-1', new LightState()));
        $this->assertSame([], $client->setGroupedLightState('group-1', new LightState()));
        $light = new Light($client, (object) ['id' => 'light-1']);
        $this->assertSame($light, $light->updateState(static function (LightState $state): void {
        }));
    }

    public function testSceneRecallByIdIsOnePut(): void
    {
        $client = $this->clientExpectingPut('/clip/v2/resource/scene/scene-1', ['recall' => ['action' => 'active']]);
        $this->assertCount(1, $client->recallScene('scene-1'));
    }
}
