<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Middleware;

use Wal3fo\LaravelSentinel\Attributes\UseCircuitBreaker;
use Wal3fo\LaravelSentinel\SentinelManager;
use ReflectionClass;
use Throwable;

final class CircuitBreakerMiddleware
{
    /**
     * @param  array<int, string>|string|null  $services
     */
    public function __construct(
        private readonly array|string|null $services = null,
        private readonly ?int $releaseDelay = null
    ) {
    }

    public function handle(object $job, callable $next): void
    {
        /** @var SentinelManager $sentinel */
        $sentinel = app(SentinelManager::class);
        $services = $this->resolveServices($job);

        if ($services === []) {
            $next($job);

            return;
        }

        $activeProbes = [];

        foreach ($services as $service) {
            if ($sentinel->isOpen($service)) {
                $this->releaseJob($job, $this->releaseDelay ?? $sentinel->releaseDelayFor($service));

                return;
            }

            if ($sentinel->isHalfOpen($service) && ! $sentinel->acquireHalfOpenProbe($service)) {
                $this->releaseJob($job, $this->releaseDelay ?? $sentinel->releaseDelayFor($service));

                return;
            }

            if ($sentinel->isHalfOpen($service)) {
                $activeProbes[] = $service;
            }
        }

        try {
            $next($job);
            foreach ($services as $service) {
                $sentinel->recordSuccess($service);
            }
        } catch (Throwable $throwable) {
            foreach ($services as $service) {
                $sentinel->recordFailure($service, $throwable);
            }

            throw $throwable;
        } finally {
            foreach ($activeProbes as $service) {
                $sentinel->releaseHalfOpenProbe($service);
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveServices(object $job): array
    {
        if ($this->services !== null) {
            return array_values(array_unique(array_filter((array) $this->services)));
        }

        $reflection = new ReflectionClass($job);
        $attributes = $reflection->getAttributes(UseCircuitBreaker::class);

        $services = [];
        foreach ($attributes as $attribute) {
            /** @var UseCircuitBreaker $instance */
            $instance = $attribute->newInstance();
            $services = array_merge($services, $instance->services);
        }

        return array_values(array_unique(array_filter($services)));
    }

    private function releaseJob(object $job, int $delay): void
    {
        if (method_exists($job, 'release')) {
            $job->release($delay);
        }
    }
}
