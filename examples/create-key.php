<?php
/**
 * Create an application key on the bridge.
 *
 * Press the link button on the bridge, then run:
 *   HUE_BRIDGE=192.168.1.10 php examples/create-key.php
 */
require __DIR__ . '/../vendor/autoload.php';

use Phphue\Client;
use Phphue\Transport\Exception\LinkButtonNotPressedException;

$host = getenv('HUE_BRIDGE') ?: '192.168.1.10';
$client = new Client($host);

try {
    $key = $client->createApplicationKey('phphue-example', 'cli', true);

    echo "Application key: {$key->username}\n";
    if (isset($key->clientkey)) {
        echo "Client key:      {$key->clientkey}\n";
    }
} catch (LinkButtonNotPressedException $e) {
    fwrite(STDERR, "Press the link button on the bridge first, then retry.\n");
    exit(1);
}
