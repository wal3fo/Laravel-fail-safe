<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Events;

use Wal3fo\LaravelSentinel\Enums\CircuitState;

abstract class CircuitStateChanged
{
    /**
     * @param  array<string, mixed>  $status
     */
    public function __construct(
        public readonly string $service,
        public readonly CircuitState $state,
        public readonly array $status
    ) {
    }
}
