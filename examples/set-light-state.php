<?php
/**
 * Turn the first light on, set it to a warm orange at 80% brightness.
 *
 *   HUE_BRIDGE=192.168.1.10 HUE_APP_KEY=xxxxx php examples/set-light-state.php
 */

use Phphue\State\LightState;

/** @var \Phphue\Client $client */
$client = require __DIR__ . '/common.php';

$lights = $client->getLights();

if ($lights === []) {
    fwrite(STDERR, "No lights found.\n");
    exit(1);
}

$light = $lights[0];
echo "Updating light {$light->getId()} ({$light->getName()})\n";

// Fluent state builder, applied in a single PUT.
$state = (new LightState())
    ->on()
    ->brightness(80)
    ->colorRGB(255, 120, 0)
    ->transition(400);

$light->applyState($state);

echo "Done.\n";
