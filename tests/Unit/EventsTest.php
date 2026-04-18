<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests\Unit;

use Illuminate\Support\Facades\Event;
use Wal3fo\LaravelSentinel\Events\CircuitClosed;
use Wal3fo\LaravelSentinel\Events\CircuitHalfOpened;
use Wal3fo\LaravelSentinel\Events\CircuitOpened;
use Wal3fo\LaravelSentinel\SentinelManager;
use Wal3fo\LaravelSentinel\Tests\TestCase;
use RuntimeException;

final class EventsTest extends TestCase
{
    public function test_it_dispatches_state_transition_events(): void
    {
        Event::fake([CircuitOpened::class, CircuitHalfOpened::class, CircuitClosed::class]);

        $sentinel = $this->app->make(SentinelManager::class);

        $sentinel->recordFailure('mail', new RuntimeException('down'));
        $sentinel->recordFailure('mail', new RuntimeException('down'));
        $sentinel->recordFailure('mail', new RuntimeException('down'));

        Event::assertDispatched(CircuitOpened::class);

        $this->clock->travelSeconds(65);
        $this->assertTrue($sentinel->isHalfOpen('mail'));
        Event::assertDispatched(CircuitHalfOpened::class);

        $sentinel->recordSuccess('mail');
        Event::assertDispatched(CircuitClosed::class);
    }
}
