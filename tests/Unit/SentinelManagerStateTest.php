<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests\Unit;

use Wal3fo\LaravelSentinel\Enums\CircuitState;
use Wal3fo\LaravelSentinel\Events\CircuitHalfOpened;
use Wal3fo\LaravelSentinel\Events\CircuitOpened;
use Wal3fo\LaravelSentinel\SentinelManager;
use Wal3fo\LaravelSentinel\Tests\TestCase;
use RuntimeException;

final class SentinelManagerStateTest extends TestCase
{
    public function test_it_opens_when_failure_threshold_is_reached(): void
    {
        $sentinel = $this->app->make(SentinelManager::class);

        $sentinel->recordFailure('mail', new RuntimeException('outage'));
        $sentinel->recordFailure('mail', new RuntimeException('outage'));
        $sentinel->recordFailure('mail', new RuntimeException('outage'));

        $status = $sentinel->status('mail');

        $this->assertSame(CircuitState::Open->value, $status['state']);
        $this->assertSame(3, $status['attempts']);
        $this->assertSame(3, $status['failures']);
        $this->assertSame(100.0, $status['failure_rate']);
        $this->assertNotNull($status['opened_at']);
        $this->assertNotNull($status['next_retry_at']);
    }

    public function test_open_circuit_moves_to_half_open_after_cooldown(): void
    {
        $sentinel = $this->app->make(SentinelManager::class);

        $sentinel->forceOpen('crm');
        $this->assertTrue($sentinel->isOpen('crm'));

        $this->clock->travelSeconds(15);

        $this->assertTrue($sentinel->isHalfOpen('crm'));
    }

    public function test_success_in_half_open_closes_and_resets_metrics(): void
    {
        $sentinel = $this->app->make(SentinelManager::class);

        $sentinel->forceOpen('crm');
        $this->clock->travelSeconds(11);
        $this->assertTrue($sentinel->isHalfOpen('crm'));

        $sentinel->recordSuccess('crm');
        $status = $sentinel->status('crm');

        $this->assertSame(CircuitState::Closed->value, $status['state']);
        $this->assertSame(0, $status['attempts']);
        $this->assertSame(0, $status['failures']);
    }

    public function test_run_records_failures_and_throws_original_exception(): void
    {
        $this->expectException(RuntimeException::class);

        $sentinel = $this->app->make(SentinelManager::class);

        $sentinel->run('mail', function (): void {
            throw new RuntimeException('service down');
        });
    }
}
