<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Wal3fo\LaravelSentinel\SentinelServiceProvider;
use Wal3fo\LaravelSentinel\Support\Clock;
use Wal3fo\LaravelSentinel\Tests\Fixtures\FrozenClock;

abstract class TestCase extends Orchestra
{
    protected FrozenClock $clock;

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function getPackageProviders($app): array
    {
        return [SentinelServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('sentinel.enabled', true);
        $app['config']->set('sentinel.cache.prefix', 'sentinel-test');
        $app['config']->set('sentinel.defaults.threshold', 50.0);
        $app['config']->set('sentinel.defaults.min_requests', 3);
        $app['config']->set('sentinel.defaults.cooldown_seconds', 60);
        $app['config']->set('sentinel.defaults.release_delay_seconds', 15);
        $app['config']->set('sentinel.defaults.half_open_probe_limit', 1);
        $app['config']->set('sentinel.services', [
            'mail' => [],
            'crm' => [
                'threshold' => 40.0,
                'min_requests' => 2,
                'cooldown_seconds' => 10,
                'release_delay_seconds' => 5,
            ],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FrozenClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $this->app->instance(Clock::class, $this->clock);
    }
}
