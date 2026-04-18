<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Enums;

enum CircuitState: string
{
    case Closed = 'closed';
    case Open = 'open';
    case HalfOpen = 'half_open';
}
