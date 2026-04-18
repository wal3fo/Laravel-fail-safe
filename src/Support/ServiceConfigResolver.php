<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Support;

use DateTimeImmutable;

final class ServiceConfigResolver
{
    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function resolve(array $config, string $service, DateTimeImmutable $now): array
    {
        $defaults = (array) ($config['defaults'] ?? []);
        $services = (array) ($config['services'] ?? []);
        $serviceOverrides = (array) ($services[$service] ?? []);

        $resolved = array_replace_recursive($defaults, $serviceOverrides);

        if ($this->isPeakHours($resolved, $now)) {
            $peakThreshold = $resolved['peak_hours']['threshold'] ?? null;
            if (is_numeric($peakThreshold)) {
                $resolved['threshold'] = (float) $peakThreshold;
            }
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $resolved
     */
    private function isPeakHours(array $resolved, DateTimeImmutable $now): bool
    {
        $peakHours = (array) ($resolved['peak_hours'] ?? []);
        if (($peakHours['enabled'] ?? false) !== true) {
            return false;
        }

        $timezone = (string) ($peakHours['timezone'] ?? 'UTC');
        $start = (string) ($peakHours['start'] ?? '00:00');
        $end = (string) ($peakHours['end'] ?? '23:59');

        $localNow = $now->setTimezone(new \DateTimeZone($timezone))->format('H:i');

        if ($start <= $end) {
            return $localNow >= $start && $localNow <= $end;
        }

        return $localNow >= $start || $localNow <= $end;
    }
}
