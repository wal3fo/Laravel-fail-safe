<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Services;

use Wal3fo\LaravelSentinel\Contracts\FailureClassifier;
use Throwable;

final class DefaultFailureClassifier implements FailureClassifier
{
    /**
     * @param  array<int, class-string<Throwable>>  $ignoredExceptions
     */
    public function __construct(private readonly array $ignoredExceptions = [])
    {
    }

    /**
     * @param  array<string, mixed>  $serviceConfig
     */
    public function shouldCount(Throwable $throwable, string $service, array $serviceConfig = []): bool
    {
        foreach ($this->ignoredExceptions as $ignoredException) {
            if (is_a($throwable, $ignoredException)) {
                return false;
            }
        }

        return true;
    }
}
