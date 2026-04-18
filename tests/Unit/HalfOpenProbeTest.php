<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests\Unit;

use Wal3fo\LaravelSentinel\SentinelManager;
use Wal3fo\LaravelSentinel\Tests\TestCase;

final class HalfOpenProbeTest extends TestCase
{
    public function test_half_open_probe_limit_allows_only_one_probe_by_default(): void
    {
        $sentinel = $this->app->make(SentinelManager::class);

        $sentinel->forceOpen('mail');
        $this->clock->travelSeconds(61);

        $this->assertTrue($sentinel->isHalfOpen('mail'));
        $this->assertTrue($sentinel->acquireHalfOpenProbe('mail'));
        $this->assertFalse($sentinel->acquireHalfOpenProbe('mail'));

        $sentinel->releaseHalfOpenProbe('mail');
        $this->assertTrue($sentinel->acquireHalfOpenProbe('mail'));
    }
}
