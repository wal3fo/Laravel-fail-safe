<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests\Fixtures;

use DateTimeImmutable;
use Wal3fo\LaravelSentinel\Support\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $now)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }

    public function travelSeconds(int $seconds): void
    {
        $this->now = $this->now->modify(sprintf('+%d seconds', $seconds));
    }
}
