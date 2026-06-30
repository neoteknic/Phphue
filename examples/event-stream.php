<?php
/**
 * Listen to the bridge event stream for 30 seconds and print changes.
 *
 *   HUE_BRIDGE=192.168.1.10 HUE_APP_KEY=xxxxx php examples/event-stream.php
 */

use Phphue\EventStream\Event;

/** @var \Phphue\Client $client */
$client = require __DIR__ . '/common.php';

echo "Listening for 30 seconds (toggle a light to see events)...\n";

$client->eventStream(30)->listen(function (Event $event): void {
    foreach ($event->getResources() as $resource) {
        printf(
            "%-7s %-12s %s\n",
            $event->getType(),
            $resource->getType(),
            $resource->getId()
        );
    }
});

echo "Stopped.\n";
