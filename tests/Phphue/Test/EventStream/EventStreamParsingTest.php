<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Test\EventStream;

use Phphue\Client;
use Phphue\EventStream\Event;
use Phphue\EventStream\EventStream;
use Phphue\Transport\Adapter\Curl;
use Phphue\Transport\TransportInterface;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

/**
 * SSE parsing and request configuration, without any network access.
 */
#[RequiresPhpExtension('curl')]
class EventStreamParsingTest extends TestCase
{
    private function stream(?Client $client = null): EventStream
    {
        return new class($client ?? new Client('192.168.1.10', 'key')) extends EventStream {
            /**
             * Feed raw chunks as the cURL write callback would.
             *
             * @param callable(Event):mixed $onEvent
             */
            public function feed(callable $onEvent, string ...$chunks): void
            {
                $this->onEvent = $onEvent(...);
                $this->buffer = '';
                $this->stopRequested = false;

                foreach ($chunks as $chunk) {
                    $this->consume($chunk);
                }
            }

            public function pending(): string
            {
                return $this->buffer;
            }

            /** @return array<int,mixed> */
            public function options(): array
            {
                return $this->buildCurlOptions();
            }
        };
    }

    /**
     * @return list<string> "type:id" of every dispatched event
     */
    private function collect(EventStream $stream, string ...$chunks): array
    {
        $seen = [];
        $stream->feed(static function (Event $event) use (&$seen): void {
            $seen[] = $event->getType() . ':' . $event->getId();
        }, ...$chunks);

        return $seen;
    }

    public function testCrLfFramesSplitAcrossChunksAreReassembled(): void
    {
        $stream = $this->stream();

        $seen = $this->collect(
            $stream,
            ": hi\r\n\r\n",
            "id: 1\r\ndata: [{\"id\":\"a\",\"type\":\"update\"}]\r",
            "\n\r\ndata: [{\"id\":\"b\",",
            "\"type\":\"add\"}]\n\n"
        );

        $this->assertSame(['update:a', 'add:b'], $seen);
        $this->assertSame('', $stream->pending());
    }

    public function testIncompleteFrameStaysBuffered(): void
    {
        $stream = $this->stream();

        $this->assertSame([], $this->collect($stream, 'data: [{"id":"a","type":"update"}]', "\n"));
        $this->assertSame("data: [{\"id\":\"a\",\"type\":\"update\"}]\n", $stream->pending());
    }

    public function testMultiLineDataIsJoinedBeforeDecoding(): void
    {
        $seen = $this->collect(
            $this->stream(),
            "data: [{\"id\":\"a\",\ndata:\"type\":\"update\"},\ndata: {\"id\":\"b\",\"type\":\"delete\"}]\n\n"
        );

        $this->assertSame(['update:a', 'delete:b'], $seen);
    }

    public function testMalformedPayloadsAreSkipped(): void
    {
        $seen = $this->collect(
            $this->stream(),
            "data: {not json}\n\n",
            "data: {\"id\":\"object-not-list\"}\n\n",
            "event: message\nid: 9\n\n",
            "data: [1, \"text\", null, {\"id\":\"ok\",\"type\":\"update\"}]\n\n"
        );

        $this->assertSame(['update:ok'], $seen);
    }

    public function testReturningFalseStopsBeforeLaterFramesOfTheSameChunk(): void
    {
        $count = 0;
        $this->stream()->feed(static function () use (&$count): bool {
            ++$count;

            return false;
        }, "data: [{\"id\":\"a\",\"type\":\"update\"}]\n\ndata: [{\"id\":\"b\",\"type\":\"update\"}]\n\n");

        $this->assertSame(1, $count);
    }

    public function testCallbackExceptionsPropagate(): void
    {
        $this->expectException(\DomainException::class);

        $this->stream()->feed(static function (): void {
            throw new \DomainException('handler failure');
        }, "data: [{\"id\":\"a\",\"type\":\"update\"}]\n\n");
    }

    public function testEventsExposeTypedResourcesAndSkipMalformedData(): void
    {
        $resources = [];
        $this->stream()->feed(
            static function (Event $event) use (&$resources): void {
                $resources = $event->getResources();
            },
            "data: [{\"id\":\"e\",\"type\":\"update\",\"data\":[{\"id\":\"l1\",\"type\":\"light\"},42,\"x\"]}]\n\n"
        );

        $this->assertCount(1, $resources);
        $this->assertInstanceOf(\Phphue\Resource\Light::class, $resources[0]);
        $this->assertSame('l1', $resources[0]->getId());
    }

    public function testRequestTargetsBridgeWithKeepAliveProbes(): void
    {
        $options = $this->stream()->options();

        $this->assertSame('https://192.168.1.10' . EventStream::PATH, $options[CURLOPT_URL]);
        $this->assertContains('hue-application-key: key', $options[CURLOPT_HTTPHEADER]);
        $this->assertContains('Accept: text/event-stream', $options[CURLOPT_HTTPHEADER]);
        $this->assertSame(0, $options[CURLOPT_TIMEOUT]);
        $this->assertTrue($options[CURLOPT_NOSIGNAL]);
        $this->assertSame(1, $options[CURLOPT_TCP_KEEPALIVE]);
        $this->assertSame(EventStream::KEEPALIVE_IDLE, $options[CURLOPT_TCP_KEEPIDLE]);
        $this->assertSame(EventStream::KEEPALIVE_INTERVAL, $options[CURLOPT_TCP_KEEPINTVL]);
    }

    public function testTlsPolicyAndPinnedCaAreInheritedFromCurlAdapter(): void
    {
        $client = new Client('192.168.1.10', 'key', sslVerify: false);
        $client->getTransport()->setAdapter(new Curl(true, 4, 'hue-ca.pem'));

        $options = $this->stream($client)->options();

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(2, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertSame('hue-ca.pem', $options[CURLOPT_CAINFO]);
    }

    public function testCaBundleIsIgnoredWhileVerificationIsDisabled(): void
    {
        $client = new Client('192.168.1.10', 'key');
        $client->getTransport()->setAdapter(new Curl(false, 4, 'hue-ca.pem'));

        $options = $this->stream($client)->options();

        $this->assertFalse($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertSame(0, $options[CURLOPT_SSL_VERIFYHOST]);
        $this->assertArrayNotHasKey(CURLOPT_CAINFO, $options);
    }

    public function testCustomTransportFallsBackToClientTlsPolicy(): void
    {
        $client = new Client('192.168.1.10', 'key', sslVerify: true);
        $client->setTransport($this->createStub(TransportInterface::class));

        $options = $this->stream($client)->options();

        $this->assertTrue($options[CURLOPT_SSL_VERIFYPEER]);
        $this->assertArrayNotHasKey(CURLOPT_CAINFO, $options);
    }
}
