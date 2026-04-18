<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests\Feature;

use Wal3fo\LaravelSentinel\Middleware\CircuitBreakerMiddleware;
use Wal3fo\LaravelSentinel\SentinelManager;
use Wal3fo\LaravelSentinel\Tests\Fixtures\AttributedMailJob;
use Wal3fo\LaravelSentinel\Tests\Fixtures\FakeQueueJob;
use Wal3fo\LaravelSentinel\Tests\TestCase;
use RuntimeException;

final class CircuitBreakerMiddlewareTest extends TestCase
{
    public function test_it_releases_job_when_circuit_is_open(): void
    {
        $sentinel = $this->app->make(SentinelManager::class);
        $sentinel->forceOpen('mail');

        $middleware = new CircuitBreakerMiddleware('mail');
        $job = new FakeQueueJob();
        $executed = false;

        $middleware->handle($job, function () use (&$executed): void {
            $executed = true;
        });

        $this->assertFalse($executed);
        $this->assertSame(15, $job->releasedWithDelay);
        $this->assertSame(1, $job->releasedCount);
    }

    public function test_it_records_success_for_multiple_services(): void
    {
        $middleware = new CircuitBreakerMiddleware(['mail', 'crm']);
        $job = new FakeQueueJob();

        $middleware->handle($job, function (): void {
            // success path
        });

        $sentinel = $this->app->make(SentinelManager::class);
        $mail = $sentinel->status('mail');
        $crm = $sentinel->status('crm');

        $this->assertSame(1, $mail['attempts']);
        $this->assertSame(1, $crm['attempts']);
        $this->assertSame(0, $mail['failures']);
        $this->assertSame(0, $crm['failures']);
    }

    public function test_it_records_failures_and_rethrows(): void
    {
        $this->expectException(RuntimeException::class);

        $middleware = new CircuitBreakerMiddleware('mail');
        $job = new FakeQueueJob();

        try {
            $middleware->handle($job, function (): void {
                throw new RuntimeException('boom');
            });
        } finally {
            $sentinel = $this->app->make(SentinelManager::class);
            $status = $sentinel->status('mail');
            $this->assertSame(1, $status['attempts']);
            $this->assertSame(1, $status['failures']);
        }
    }

    public function test_it_resolves_service_from_attribute_when_not_passed_to_middleware(): void
    {
        $middleware = new CircuitBreakerMiddleware();
        $job = new AttributedMailJob();

        $middleware->handle($job, function (): void {
            // success
        });

        $sentinel = $this->app->make(SentinelManager::class);
        $status = $sentinel->status('mail');

        $this->assertSame(1, $status['attempts']);
    }
}
