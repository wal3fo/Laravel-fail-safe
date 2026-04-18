<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests\Unit;

use Wal3fo\LaravelSentinel\SentinelManager;
use Wal3fo\LaravelSentinel\Tests\Fixtures\BusinessException;
use Wal3fo\LaravelSentinel\Tests\TestCase;
use RuntimeException;

final class FailureClassifierTest extends TestCase
{
    public function test_business_exception_is_ignored_by_default_classifier(): void
    {
        $sentinel = $this->app->make(SentinelManager::class);

        $sentinel->recordFailure('mail', new BusinessException('ignore'));

        $status = $sentinel->status('mail');
        $this->assertSame(1, $status['attempts']);
        $this->assertSame(0, $status['failures']);
        $this->assertSame('closed', $status['state']);
    }

    public function test_runtime_exception_counts_as_outage_failure(): void
    {
        $sentinel = $this->app->make(SentinelManager::class);

        $sentinel->recordFailure('mail', new RuntimeException('outage'));

        $status = $sentinel->status('mail');
        $this->assertSame(1, $status['attempts']);
        $this->assertSame(1, $status['failures']);
    }
}
