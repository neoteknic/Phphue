<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource\Concern;

use Phphue\Resource\GroupedLight;

/**
 * Shared accessors for resources exposing `children` and `services` references
 * (room, zone, bridge_home, device).
 */
trait HasServices
{
    /**
     * Child resource references ({rid, rtype}).
     *
     * @return array<int,\stdClass>
     */
    public function getChildren(): array
    {
        $children = $this->data->children ?? [];

        return is_array($children) ? $children : [];
    }

    /**
     * Service resource references ({rid, rtype}).
     *
     * @return array<int,\stdClass>
     */
    public function getServices(): array
    {
        $services = $this->data->services ?? [];

        return is_array($services) ? $services : [];
    }

    /**
     * Service references filtered by rtype.
     *
     * @return array<int,\stdClass>
     */
    public function getServicesByType(string $rtype): array
    {
        return array_values(array_filter(
            $this->getServices(),
            static fn ($service) => isset($service->rtype) && $service->rtype === $rtype
        ));
    }

    /**
     * Resolve the grouped_light service controlling this group, if any.
     */
    public function getGroupedLight(): ?GroupedLight
    {
        $service = array_first($this->getServicesByType('grouped_light'));

        if (! isset($service->rid)) {
            return null;
        }

        /** @var GroupedLight $groupedLight */
        $groupedLight = $this->client->getResourceById('grouped_light', (string) $service->rid);

        return $groupedLight;
    }
}
