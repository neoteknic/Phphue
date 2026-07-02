# Phphue

PHP client for the **Philips Hue API V2** (the CLIP v2 REST API exposed by Hue bridges
running recent firmware).

Phphue is a spiritual successor to [Phue](https://github.com/neoteknic/Phue), which targets
the now-deprecated Hue API v1 (`/api/{username}/...`). Phphue keeps the familiar
*Client / Command / Transport / Resource* architecture but is built around the uniform,
resource-oriented CLIP v2 API: `https://{bridge}/clip/v2/resource/{type}`, the
`hue-application-key` header, the `{data, errors}` envelope, and the Server-Sent Events
stream.

> **Scope:** This release covers the full CLIP v2 REST API and the event stream. The
> real-time **Entertainment** streaming API (DTLS) is intentionally **out of scope for now**
> and planned as a follow-up — the architecture leaves room for it
> (`Resource\Entertainment*`, separated transport).

## Requirements

- PHP 8.5+
- `ext-curl` (default transport adapter and event stream)

## Installation

```bash
composer require neoteknic/phphue
```

## Getting started

### 1. Create an application key

Press the link button on the bridge, then:

```php
use Phphue\Client;

$client = new Client('192.168.1.10');                 // bridge IP or hostname
$key = $client->createApplicationKey('my-app', 'cli', true); // true => also a clientkey

echo $key->username;   // <- your application key, store it
```

### 2. Talk to the bridge

```php
use Phphue\Client;
use Phphue\State\LightState;

$client = new Client('192.168.1.10', 'YOUR_APPLICATION_KEY');

// Typed accessors
foreach ($client->getLights() as $light) {
    echo $light->getName(), ' ', $light->isOn() ? 'on' : 'off', "\n";
}

// Fluent state, applied in a single PUT
$light = $client->getLights()[0];
$light->applyState(
    (new LightState())->on()->brightness(80)->colorRGB(255, 120, 0)->transition(400)
);

// Or the quick setters
$light->setColorTemperature(300);   // mired/mirek
$light->off();
```

### Dynamic effects

The Hue API V2 exposes built-in dynamic effects (`candle`, `fire`, `sparkle`,
`glisten`, `opal`, `prism`) — there is no V1 equivalent beyond the old colour loop.

```php
use Phphue\State\LightState;

// Which effects does this specific light support?
$light->getEffectValues();          // ['no_effect', 'candle', 'sparkle', ...]

// Start an effect
$light->setEffect(LightState::EFFECT_SPARKLE);

// The effects_v2 variant lets you tune the speed (0-1)
$light->setEffectV2(LightState::EFFECT_PRISM, speed: 0.6);

// Stop the running effect
$light->setEffect(LightState::EFFECT_NO_EFFECT);

// ...or combine it with other state in one PUT
$light->applyState(
    (new LightState())->on()->brightness(80)->effectV2(LightState::EFFECT_FIRE, 0.4)
);
```

## Architecture

| Layer | Classes | Role |
|-------|---------|------|
| Client | `Phphue\Client` | Holds the host + application key, exposes accessors, sends commands |
| Commands | `Phphue\Command\*` | Generic CRUD over any resource type (`GetResources`, `GetResourceById`, `CreateResource`, `UpdateResource`, `DeleteResource`, `GetAllResources`, `CreateApplicationKey`) |
| Transport | `Phphue\Transport\Http` + `Adapter\Curl` | HTTPS, `hue-application-key` header, `{data, errors}` parsing, status-code → exception mapping |
| Resources | `Phphue\Resource\*` | Typed wrappers (`Light`, `Room`, `Zone`, `Scene`, `Device`, ...) built by `ResourceFactory`; unknown types fall back to `GenericResource` |
| State | `Phphue\State\LightState`, `Color` | PUT-body builder and RGB ⇄ xy / Kelvin ⇄ mirek conversions |
| Events | `Phphue\EventStream\EventStream`, `Event` | Consumes `GET /eventstream/clip/v2` (SSE) |

### Generic access (every resource type)

Because the CLIP v2 API is uniform, every route is reachable generically:

```php
$client->getResources('motion');                 // AbstractResource[]
$client->getResourceById('device', $id);          // AbstractResource
$client->createResource('room', ['type' => 'room', 'metadata' => ['name' => 'Office']]);
$client->updateResource('light', $id, ['on' => ['on' => true]]);
$client->deleteResource('scene', $id);
$client->getAllResources();                        // GET /clip/v2/resource
```

Typed convenience accessors exist for the common types: `getLights()`, `getGroupedLights()`,
`getRooms()`, `getZones()`, `getScenes()`, `getSmartScenes()`, `getDevices()`,
`getDevicePowers()`, `getBridge()`, `getBridgeHomes()`, `getMotionSensors()`,
`getTemperatures()`, `getLightLevels()`, `getButtons()`, `getEntertainmentConfigurations()`.

Resource types with a dedicated wrapper: `light`, `grouped_light`, `room`, `zone`,
`bridge`, `bridge_home`, `scene`, `smart_scene`, `device`, `device_power`, `motion`,
`temperature`, `light_level`, `button`, `relative_rotary`, `contact`, `tamper`,
`zigbee_connectivity`, `entertainment`, `entertainment_configuration`, `behavior_script`,
`behavior_instance`, `geofence_client`, `geolocation`, `homekit`, `matter`. Any other type
(`service_group`, `zgp_connectivity`, `matter_fabric`, `public_image`, ...) is returned as a
`GenericResource` and remains fully usable through `attr()`, `getRaw()`, `update()` and
`delete()`.

### Event stream

```php
use Phphue\EventStream\Event;

$client->eventStream(/* maxSeconds */ 0)->listen(function (Event $event) {
    foreach ($event->getResources() as $resource) {
        echo $event->getType(), ' ', $resource->getType(), ' ', $resource->getId(), "\n";
    }
    // return false to stop listening
});
```

## TLS

Hue bridges present a self-signed certificate, so certificate verification is **off by
default**. To pin the official Hue CA, enable it and pass the bundle to the adapter:

```php
$client = new Client('192.168.1.10', $key, sslVerify: true);
$client->getTransport()->setAdapter(new \Phphue\Transport\Adapter\Curl(true, 10, '/path/to/huebridge_cacert.pem'));
```

## Examples

See the [`examples/`](examples) directory. Set `HUE_BRIDGE` and `HUE_APP_KEY` env vars:

```bash
HUE_BRIDGE=192.168.1.10 php examples/create-key.php       # press the link button first
HUE_BRIDGE=192.168.1.10 HUE_APP_KEY=xxxxx php examples/list-lights.php
HUE_BRIDGE=192.168.1.10 HUE_APP_KEY=xxxxx php examples/set-light-state.php
HUE_BRIDGE=192.168.1.10 HUE_APP_KEY=xxxxx php examples/event-stream.php
```

## Development

```bash
composer install
composer phpunit     # unit tests
composer phpstan     # static analysis (level 6)
```

## License

BSD-3-Clause. See [LICENSE](LICENSE). Portions derived from the Phue project.
