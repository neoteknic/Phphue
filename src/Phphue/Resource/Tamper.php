<?php
/**
 * Phphue: Philips Hue PHP Client (Hue API V2 / CLIP v2)
 *
 * @license BSD-3-Clause
 */
namespace Phphue\Resource;

/**
 * `tamper` resource - tamper detection state of a device.
 */
class Tamper extends AbstractResource
{
    /**
     * Tamper reports ({source, state, changed}).
     *
     * @return array<int,\stdClass>
     */
    public function getReports(): array
    {
        $reports = $this->data->tamper_reports ?? [];

        return is_array($reports) ? $reports : [];
    }
}
