<?php
/**
 * List rooms and toggle the grouped light of the first one.
 *
 *   HUE_BRIDGE=192.168.1.10 HUE_APP_KEY=xxxxx php examples/list-rooms.php
 */

/** @var \Phphue\Client $client */
$client = require __DIR__ . '/common.php';

foreach ($client->getRooms() as $room) {
    $grouped = $room->getGroupedLight();

    printf(
        "[%s] %-20s lights:%d  %s\n",
        $room->getId(),
        $room->getName() ?? '(unnamed)',
        count($room->getChildren()),
        $grouped !== null ? ($grouped->isOn() ? 'on' : 'off') : 'no grouped_light'
    );
}
