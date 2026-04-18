<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final class UseCircuitBreaker
{
    /**
     * @param  array<int, string>  $services
     */
    public function __construct(public readonly array $services)
    {
    }
}
