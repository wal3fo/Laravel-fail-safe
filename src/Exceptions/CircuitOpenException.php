<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Exceptions;

use RuntimeException;

final class CircuitOpenException extends RuntimeException
{
    public static function forService(string $service): self
    {
        return new self(sprintf('Sentinel circuit is open for service [%s].', $service));
    }
}
