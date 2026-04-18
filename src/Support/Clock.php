<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Support;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
