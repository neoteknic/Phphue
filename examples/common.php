<?php
/**
 * Shared bootstrap for the Phphue examples.
 *
 * Set the bridge address and application key through environment variables:
 *   HUE_BRIDGE=192.168.1.10 HUE_APP_KEY=xxxxx php examples/list-lights.php
 */
require __DIR__ . '/../vendor/autoload.php';

use Phphue\Client;

$host = getenv('HUE_BRIDGE') ?: '192.168.1.10';
$applicationKey = getenv('HUE_APP_KEY') ?: null;

// Bridges ship a self-signed certificate, so TLS verification is disabled by default.
return new Client($host, $applicationKey);
