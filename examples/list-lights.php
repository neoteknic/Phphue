<?php
/**
 * List every light known by the bridge.
 *
 *   HUE_BRIDGE=192.168.1.10 HUE_APP_KEY=xxxxx php examples/list-lights.php
 */

/** @var \Phphue\Client $client */
$client = require __DIR__ . '/common.php';

foreach ($client->getLights() as $light) {
    $brightness = $light->getBrightness();

    printf(
        "[%s] %-25s %s%s\n",
        $light->getId(),
        $light->getName() ?? '(unnamed)',
        $light->isOn() ? 'on' : 'off',
        $brightness !== null ? sprintf(' @ %d%%', (int) round($brightness)) : ''
    );
}
