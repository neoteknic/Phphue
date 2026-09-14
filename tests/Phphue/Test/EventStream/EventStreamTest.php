<?php
namespace Phphue\Test\EventStream;

use Phphue\Client;
use Phphue\Test\Support\LocalHttpServer;
use Phphue\Transport\Exception\ConnectionException;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('curl')]
class EventStreamTest extends TestCase
{
    public function testReturningFalseStopsNormallyAfterFirstEvent(): void
    {
        $server = new LocalHttpServer();
        $count = 0;
        (new Client($server->url))->eventStream(2)->listen(function () use (&$count) {
            ++$count;
            return false;
        });
        $this->assertSame(1, $count);
    }

    public function testExplicitStopDoesNotDispatchRemainingEventsInFrame(): void
    {
        $server = new LocalHttpServer();
        $stream = (new Client($server->url))->eventStream(2);
        $count = 0;
        $stream->listen(function () use ($stream, &$count): void {
            ++$count;
            $stream->stop();
        });
        $this->assertSame(1, $count);
    }

    public function testListeningDurationEndsNormallyAfterSuccessfulConnection(): void
    {
        $server = new LocalHttpServer();
        $count = 0;
        (new Client($server->url . '/idle'))->eventStream(1)->listen(function () use (&$count): void {
            ++$count;
        });
        $this->assertSame(0, $count);
    }

    public function testTimeoutBeforeResponseIsNotHiddenAsNormalStop(): void
    {
        $server = new LocalHttpServer();
        $this->expectException(ConnectionException::class);
        (new Client($server->url . '/stall'))->eventStream(1)->listen(static fn () => false);
    }
}
