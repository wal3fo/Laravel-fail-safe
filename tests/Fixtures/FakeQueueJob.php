<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests\Fixtures;

class FakeQueueJob
{
    public ?int $releasedWithDelay = null;

    public int $releasedCount = 0;

    public function release(int $delay): void
    {
        $this->releasedWithDelay = $delay;
        $this->releasedCount++;
    }
}
