<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

use Phphue\Client;

/**
 * Builds typed resource wrappers from the raw CLIP v2 payloads.
 *
 * Any resource type without a dedicated class falls back to {@see GenericResource},
 * so unknown / future types are still usable (read attributes, update, delete).
 */
class ResourceFactory
{
    /**
     * Resource type ("type" field) to wrapper class map.
     *
     * @var array<string,class-string<AbstractResource>>
     */
    public static array $map = [
        'light' => Light::class,
        'grouped_light' => GroupedLight::class,
        'room' => Room::class,
        'zone' => Zone::class,
        'bridge_home' => BridgeHome::class,
        'scene' => Scene::class,
        'smart_scene' => SmartScene::class,
        'device' => Device::class,
        'device_power' => DevicePower::class,
        'bridge' => Bridge::class,
        'motion' => Motion::class,
        'temperature' => Temperature::class,
        'light_level' => LightLevel::class,
        'button' => Button::class,
        'relative_rotary' => RelativeRotary::class,
        'contact' => Contact::class,
        'tamper' => Tamper::class,
        'zigbee_connectivity' => ZigbeeConnectivity::class,
        'entertainment' => Entertainment::class,
        'entertainment_configuration' => EntertainmentConfiguration::class,
        'behavior_script' => BehaviorScript::class,
        'behavior_instance' => BehaviorInstance::class,
        'geofence_client' => GeofenceClient::class,
        'geolocation' => Geolocation::class,
        'homekit' => Homekit::class,
        'matter' => Matter::class,
    ];

    /**
     * Build a single typed resource from a raw payload.
     */
    public static function create(Client $client, \stdClass $data): AbstractResource
    {
        $type = isset($data->type) ? (string) $data->type : '';
        $class = self::$map[$type] ?? GenericResource::class;

        return new $class($client, $data);
    }

    /**
     * Build a collection of typed resources from a list of raw payloads.
     *
     * @param array<int,\stdClass> $items
     * @return AbstractResource[]
     */
    public static function createCollection(Client $client, array $items): array
    {
        return array_map(
            static fn (\stdClass $item) => self::create($client, $item),
            array_values($items)
        );
    }
}
