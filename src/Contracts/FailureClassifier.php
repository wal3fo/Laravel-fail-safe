<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Contracts;

use Throwable;

interface FailureClassifier
{
    /**
     * Decide whether the thrown exception represents an infrastructure outage.
     *
     * @param  array<string, mixed>  $serviceConfig
     */
    public function shouldCount(Throwable $throwable, string $service, array $serviceConfig = []): bool;
}
