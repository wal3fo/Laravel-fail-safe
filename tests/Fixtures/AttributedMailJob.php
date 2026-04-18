<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests\Fixtures;

use Wal3fo\LaravelSentinel\Attributes\UseCircuitBreaker;

#[UseCircuitBreaker(['mail'])]
final class AttributedMailJob extends FakeQueueJob
{
}
