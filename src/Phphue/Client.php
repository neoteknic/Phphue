<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue;

use Phphue\Command\CommandInterface;
use Phphue\Command\CreateApplicationKey;
use Phphue\Command\CreateResource;
use Phphue\Command\DeleteResource;
use Phphue\Command\GetAllResources;
use Phphue\Command\GetResourceById;
use Phphue\Command\GetResources;
use Phphue\Command\UpdateResource;
use Phphue\EventStream\EventStream;
use Phphue\Resource\AbstractResource;
use Phphue\Resource\Bridge;
use Phphue\Resource\BridgeHome;
use Phphue\Resource\Button;
use Phphue\Resource\Device;
use Phphue\Resource\DevicePower;
use Phphue\Resource\EntertainmentConfiguration;
use Phphue\Resource\GroupedLight;
use Phphue\Resource\Light;
use Phphue\Resource\LightLevel;
use Phphue\Resource\Motion;
use Phphue\Resource\Room;
use Phphue\Resource\Scene;
use Phphue\Resource\SmartScene;
use Phphue\Resource\Temperature;
use Phphue\Resource\Zone;
use Phphue\Transport\Http;
use Phphue\Transport\TransportInterface;

/**
 * Client for connecting to a Philips Hue bridge through the CLIP v2 API.
 */
class Client
{
    protected string $host;

    protected ?string $applicationKey;

    protected bool $sslVerify;

    protected TransportInterface $transport;

    public function __construct(string $host, ?string $applicationKey = null, bool $sslVerify = false)
    {
        $this->setHost($host);
        $this->setApplicationKey($applicationKey);
        $this->sslVerify = $sslVerify;
        $this->setTransport(new Http($this));
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function setHost(string $host): static
    {
        $this->host = $host;

        return $this;
    }

    public function getApplicationKey(): ?string
    {
        return $this->applicationKey;
    }

    public function setApplicationKey(?string $applicationKey): static
    {
        $this->applicationKey = $applicationKey;

        return $this;
    }

    public function getSslVerify(): bool
    {
        return $this->sslVerify;
    }

    public function setSslVerify(bool $sslVerify): static
    {
        $this->sslVerify = $sslVerify;

        return $this;
    }

    public function getTransport(): TransportInterface
    {
        return $this->transport;
    }

    public function setTransport(TransportInterface $transport): static
    {
        $this->transport = $transport;

        return $this;
    }

    public function sendCommand(CommandInterface $command): mixed
    {
        return $command->send($this);
    }

    /*
     * --------------------------------------------------------------------
     * Generic resource access (covers every CLIP v2 resource type)
     * --------------------------------------------------------------------
     */

    /**
     * GET /clip/v2/resource - every resource known by the bridge.
     *
     * @return AbstractResource[]
     */
    public function getAllResources(): array
    {
        return $this->sendCommand(new GetAllResources());
    }

    /**
     * GET /clip/v2/resource/{type}
     *
     * @return AbstractResource[]
     */
    public function getResources(string $type): array
    {
        return $this->sendCommand(new GetResources($type));
    }

    /**
     * GET /clip/v2/resource/{type}/{id}
     */
    public function getResourceById(string $type, string $id): AbstractResource
    {
        return $this->sendCommand(new GetResourceById($type, $id));
    }

    /**
     * POST /clip/v2/resource/{type}
     *
     * @param array<string,mixed>|object $body
     * @return array<int,\stdClass> Created resource identifiers
     */
    public function createResource(string $type, array|object $body): array
    {
        return $this->sendCommand(new CreateResource($type, $body));
    }

    /**
     * PUT /clip/v2/resource/{type}/{id}
     *
     * @param array<string,mixed>|object $body
     * @return array<int,\stdClass> Updated resource identifiers
     */
    public function updateResource(string $type, string $id, array|object $body): array
    {
        return $this->sendCommand(new UpdateResource($type, $id, $body));
    }

    /**
     * DELETE /clip/v2/resource/{type}/{id}
     *
     * @return array<int,\stdClass> Deleted resource identifiers
     */
    public function deleteResource(string $type, string $id): array
    {
        return $this->sendCommand(new DeleteResource($type, $id));
    }

    /*
     * --------------------------------------------------------------------
     * Typed convenience accessors
     * --------------------------------------------------------------------
     */

    /**
     * Fetch all resources of a type and keep only the expected wrapper class.
     *
     * @template T of AbstractResource
     * @param class-string<T> $class
     * @return T[]
     */
    private function typedResources(string $type, string $class): array
    {
        return array_values(array_filter(
            $this->getResources($type),
            static fn (AbstractResource $resource): bool => $resource instanceof $class
        ));
    }

    /** @return Light[] */
    public function getLights(): array
    {
        return $this->typedResources('light', Light::class);
    }

    public function getLight(string $id): Light
    {
        /** @var Light $light */
        $light = $this->getResourceById('light', $id);

        return $light;
    }

    /** @return GroupedLight[] */
    public function getGroupedLights(): array
    {
        return $this->typedResources('grouped_light', GroupedLight::class);
    }

    public function getGroupedLight(string $id): GroupedLight
    {
        /** @var GroupedLight $light */
        $light = $this->getResourceById('grouped_light', $id);

        return $light;
    }

    /** @return Room[] */
    public function getRooms(): array
    {
        return $this->typedResources('room', Room::class);
    }

    public function getRoom(string $id): Room
    {
        /** @var Room $room */
        $room = $this->getResourceById('room', $id);

        return $room;
    }

    /** @return Zone[] */
    public function getZones(): array
    {
        return $this->typedResources('zone', Zone::class);
    }

    public function getZone(string $id): Zone
    {
        /** @var Zone $zone */
        $zone = $this->getResourceById('zone', $id);

        return $zone;
    }

    /** @return Scene[] */
    public function getScenes(): array
    {
        return $this->typedResources('scene', Scene::class);
    }

    public function getScene(string $id): Scene
    {
        /** @var Scene $scene */
        $scene = $this->getResourceById('scene', $id);

        return $scene;
    }

    /** @return SmartScene[] */
    public function getSmartScenes(): array
    {
        return $this->typedResources('smart_scene', SmartScene::class);
    }

    /** @return Device[] */
    public function getDevices(): array
    {
        return $this->typedResources('device', Device::class);
    }

    public function getDevice(string $id): Device
    {
        /** @var Device $device */
        $device = $this->getResourceById('device', $id);

        return $device;
    }

    /** @return DevicePower[] */
    public function getDevicePowers(): array
    {
        return $this->typedResources('device_power', DevicePower::class);
    }

    /** @return Bridge[] */
    public function getBridges(): array
    {
        return $this->typedResources('bridge', Bridge::class);
    }

    /**
     * The bridge exposes a single "bridge" resource.
     */
    public function getBridge(): ?Bridge
    {
        return array_first($this->getBridges());
    }

    /** @return BridgeHome[] */
    public function getBridgeHomes(): array
    {
        return $this->typedResources('bridge_home', BridgeHome::class);
    }

    /** @return Motion[] */
    public function getMotionSensors(): array
    {
        return $this->typedResources('motion', Motion::class);
    }

    /** @return Temperature[] */
    public function getTemperatures(): array
    {
        return $this->typedResources('temperature', Temperature::class);
    }

    /** @return LightLevel[] */
    public function getLightLevels(): array
    {
        return $this->typedResources('light_level', LightLevel::class);
    }

    /** @return Button[] */
    public function getButtons(): array
    {
        return $this->typedResources('button', Button::class);
    }

    /** @return EntertainmentConfiguration[] */
    public function getEntertainmentConfigurations(): array
    {
        return $this->typedResources('entertainment_configuration', EntertainmentConfiguration::class);
    }

    /**
     * Build a consumer for the CLIP v2 Server-Sent Events stream.
     *
     * @param int $maxSeconds Maximum listening duration in seconds (0 = no limit)
     */
    public function eventStream(int $maxSeconds = 0): EventStream
    {
        return new EventStream($this, $maxSeconds);
    }

    /**
     * Create an application key (and optionally a client key) on the bridge.
     *
     * The bridge link button must be pressed within 30 seconds before calling this.
     * On success the key is stored on the client for subsequent requests.
     *
     * @return \stdClass {username, clientkey?}
     */
    public function createApplicationKey(string $appName, string $instanceName = 'php', bool $generateClientKey = false): \stdClass
    {
        $result = $this->sendCommand(new CreateApplicationKey($appName, $instanceName, $generateClientKey));

        if (isset($result->username)) {
            $this->setApplicationKey((string) $result->username);
        }

        return $result;
    }
}
