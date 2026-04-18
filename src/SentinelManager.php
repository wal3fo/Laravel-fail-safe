<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel;

use Closure;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Wal3fo\LaravelSentinel\Contracts\FailureClassifier;
use Wal3fo\LaravelSentinel\Enums\CircuitState;
use Wal3fo\LaravelSentinel\Events\CircuitClosed;
use Wal3fo\LaravelSentinel\Events\CircuitHalfOpened;
use Wal3fo\LaravelSentinel\Events\CircuitOpened;
use Wal3fo\LaravelSentinel\Exceptions\CircuitOpenException;
use Wal3fo\LaravelSentinel\Support\Clock;
use Wal3fo\LaravelSentinel\Support\ServiceConfigResolver;
use Throwable;

final class SentinelManager
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly CacheFactory $cacheFactory,
        private readonly Dispatcher $events,
        private readonly FailureClassifier $failureClassifier,
        private readonly Clock $clock,
        private readonly ServiceConfigResolver $configResolver,
        private readonly array $config
    ) {
    }

    public function isOpen(string $service): bool
    {
        return $this->getCurrentState($service) === CircuitState::Open;
    }

    public function isHalfOpen(string $service): bool
    {
        return $this->getCurrentState($service) === CircuitState::HalfOpen;
    }

    public function recordSuccess(string $service): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->withLock($service, function () use ($service): void {
            $state = $this->refreshState($service);
            $metrics = $this->getMetrics($service);

            if ($state === CircuitState::HalfOpen) {
                $this->closeCircuit($service, true);

                return;
            }

            $metrics['attempts']++;
            $this->putMetrics($service, $metrics);
        });
    }

    public function recordFailure(string $service, ?Throwable $e = null): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->withLock($service, function () use ($service, $e): void {
            $state = $this->refreshState($service);
            $metrics = $this->getMetrics($service);
            $metrics['attempts']++;

            $serviceConfig = $this->serviceConfig($service);
            if ($e !== null && ! $this->failureClassifier->shouldCount($e, $service, $serviceConfig)) {
                $this->putMetrics($service, $metrics);

                return;
            }

            $metrics['failures']++;
            $this->putMetrics($service, $metrics);

            if ($state === CircuitState::HalfOpen) {
                $this->openCircuit($service);

                return;
            }

            $minRequests = (int) ($serviceConfig['min_requests'] ?? 10);
            $threshold = (float) ($serviceConfig['threshold'] ?? 50.0);
            $failureRate = $this->failureRateFromMetrics($metrics);

            if ($metrics['attempts'] >= $minRequests && $failureRate >= $threshold) {
                $this->openCircuit($service);
            }
        });
    }

    public function forceOpen(string $service): void
    {
        $this->withLock($service, fn () => $this->openCircuit($service));
    }

    public function forceClose(string $service): void
    {
        $this->withLock($service, fn () => $this->closeCircuit($service, true));
    }

    public function reset(?string $service = null): void
    {
        if ($service !== null) {
            $this->resetService($service);

            return;
        }

        $serviceNames = array_unique(array_keys((array) ($this->config['services'] ?? [])));
        foreach ($serviceNames as $serviceName) {
            $this->resetService((string) $serviceName);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function status(?string $service = null): array
    {
        if ($service !== null) {
            return $this->singleStatus($service);
        }

        $statuses = [];
        foreach (array_keys((array) ($this->config['services'] ?? [])) as $serviceName) {
            $statuses[(string) $serviceName] = $this->singleStatus((string) $serviceName);
        }

        return $statuses;
    }

    public function acquireHalfOpenProbe(string $service): bool
    {
        if (! $this->isHalfOpen($service)) {
            return true;
        }

        return $this->withLock($service, function () use ($service): bool {
            if ($this->refreshState($service) !== CircuitState::HalfOpen) {
                return true;
            }

            $limit = (int) ($this->serviceConfig($service)['half_open_probe_limit'] ?? 1);
            $probeKey = $this->probeCounterKey($service);
            $current = (int) $this->cacheStore()->get($probeKey, 0);

            if ($current >= $limit) {
                return false;
            }

            $this->cacheStore()->put($probeKey, $current + 1, $this->secondsUntil(3600));

            return true;
        });
    }

    public function releaseHalfOpenProbe(string $service): void
    {
        $this->withLock($service, function () use ($service): void {
            $probeKey = $this->probeCounterKey($service);
            $current = (int) $this->cacheStore()->get($probeKey, 0);

            if ($current <= 0) {
                $this->cacheStore()->forget($probeKey);

                return;
            }

            $this->cacheStore()->put($probeKey, $current - 1, $this->secondsUntil(3600));
        });
    }

    /**
     * @template T
     * @param  Closure():T  $callback
     * @return T
     */
    public function run(string $service, Closure $callback, ?Closure $fallback = null): mixed
    {
        if ($this->isOpen($service)) {
            if ($fallback !== null) {
                return $fallback();
            }

            throw CircuitOpenException::forService($service);
        }

        if ($this->isHalfOpen($service) && ! $this->acquireHalfOpenProbe($service)) {
            if ($fallback !== null) {
                return $fallback();
            }

            throw CircuitOpenException::forService($service);
        }

        try {
            $result = $callback();
            $this->recordSuccess($service);

            return $result;
        } catch (Throwable $throwable) {
            $this->recordFailure($service, $throwable);
            throw $throwable;
        } finally {
            if ($this->isHalfOpen($service)) {
                $this->releaseHalfOpenProbe($service);
            }
        }
    }

    public function releaseDelayFor(string $service): int
    {
        return (int) ($this->serviceConfig($service)['release_delay_seconds'] ?? 30);
    }

    /**
     * @return array<string, mixed>
     */
    private function singleStatus(string $service): array
    {
        $state = $this->getCurrentState($service);
        $metrics = $this->getMetrics($service);
        $serviceConfig = $this->serviceConfig($service);

        return [
            'service' => $service,
            'state' => $state->value,
            'threshold' => (float) ($serviceConfig['threshold'] ?? 50.0),
            'min_requests' => (int) ($serviceConfig['min_requests'] ?? 10),
            'cooldown_seconds' => (int) ($serviceConfig['cooldown_seconds'] ?? 60),
            'release_delay_seconds' => (int) ($serviceConfig['release_delay_seconds'] ?? 30),
            'half_open_probe_limit' => (int) ($serviceConfig['half_open_probe_limit'] ?? 1),
            'attempts' => (int) $metrics['attempts'],
            'failures' => (int) $metrics['failures'],
            'failure_rate' => $this->failureRateFromMetrics($metrics),
            'opened_at' => $metrics['opened_at'],
            'next_retry_at' => $metrics['next_retry_at'],
        ];
    }

    private function getCurrentState(string $service): CircuitState
    {
        return $this->withLock($service, fn () => $this->refreshState($service));
    }

    private function refreshState(string $service): CircuitState
    {
        $state = CircuitState::from((string) $this->cacheStore()->get($this->stateKey($service), CircuitState::Closed->value));

        if ($state !== CircuitState::Open) {
            return $state;
        }

        $metrics = $this->getMetrics($service);
        $nextRetryAt = $metrics['next_retry_at'];

        if ($nextRetryAt === null) {
            return $state;
        }

        if ($this->clock->now()->getTimestamp() < (int) $nextRetryAt) {
            return $state;
        }

        $this->cacheStore()->put($this->stateKey($service), CircuitState::HalfOpen->value, $this->secondsUntil(86400));
        $this->cacheStore()->forget($this->probeCounterKey($service));
        $this->events->dispatch(new CircuitHalfOpened($service, CircuitState::HalfOpen, $this->singleStatus($service)));

        return CircuitState::HalfOpen;
    }

    private function openCircuit(string $service): void
    {
        $metrics = $this->getMetrics($service);
        $serviceConfig = $this->serviceConfig($service);
        $cooldown = (int) ($serviceConfig['cooldown_seconds'] ?? 60);

        $metrics['opened_at'] = $this->clock->now()->getTimestamp();
        $metrics['next_retry_at'] = $this->clock->now()->modify(sprintf('+%d seconds', $cooldown))->getTimestamp();

        $this->putMetrics($service, $metrics);
        $this->cacheStore()->put($this->stateKey($service), CircuitState::Open->value, $this->secondsUntil($cooldown + 86400));
        $this->cacheStore()->forget($this->probeCounterKey($service));

        $this->events->dispatch(new CircuitOpened($service, CircuitState::Open, $this->singleStatus($service)));
    }

    private function closeCircuit(string $service, bool $resetCounters): void
    {
        if ($resetCounters) {
            $this->putMetrics($service, [
                'attempts' => 0,
                'failures' => 0,
                'opened_at' => null,
                'next_retry_at' => null,
            ]);
        } else {
            $metrics = $this->getMetrics($service);
            $metrics['opened_at'] = null;
            $metrics['next_retry_at'] = null;
            $this->putMetrics($service, $metrics);
        }

        $this->cacheStore()->put($this->stateKey($service), CircuitState::Closed->value, $this->secondsUntil(86400));
        $this->cacheStore()->forget($this->probeCounterKey($service));

        $this->events->dispatch(new CircuitClosed($service, CircuitState::Closed, $this->singleStatus($service)));
    }

    private function resetService(string $service): void
    {
        $this->cacheStore()->forget($this->stateKey($service));
        $this->cacheStore()->forget($this->metricsKey($service));
        $this->cacheStore()->forget($this->probeCounterKey($service));
    }

    /**
     * @return array{attempts:int,failures:int,opened_at:int|null,next_retry_at:int|null}
     */
    private function getMetrics(string $service): array
    {
        /** @var array<string, mixed> $metrics */
        $metrics = (array) $this->cacheStore()->get($this->metricsKey($service), []);

        return [
            'attempts' => (int) ($metrics['attempts'] ?? 0),
            'failures' => (int) ($metrics['failures'] ?? 0),
            'opened_at' => isset($metrics['opened_at']) ? (int) $metrics['opened_at'] : null,
            'next_retry_at' => isset($metrics['next_retry_at']) ? (int) $metrics['next_retry_at'] : null,
        ];
    }

    /**
     * @param  array{attempts:int,failures:int,opened_at:int|null,next_retry_at:int|null}  $metrics
     */
    private function putMetrics(string $service, array $metrics): void
    {
        $this->cacheStore()->put($this->metricsKey($service), $metrics, $this->secondsUntil(86400));
    }

    /**
     * @param  array{attempts:int,failures:int,opened_at:int|null,next_retry_at:int|null}  $metrics
     */
    private function failureRateFromMetrics(array $metrics): float
    {
        if ($metrics['attempts'] <= 0) {
            return 0.0;
        }

        return round(($metrics['failures'] / $metrics['attempts']) * 100, 2);
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceConfig(string $service): array
    {
        return $this->configResolver->resolve($this->config, $service, $this->clock->now());
    }

    private function stateKey(string $service): string
    {
        return sprintf('%s:%s:state', $this->cachePrefix(), $service);
    }

    private function metricsKey(string $service): string
    {
        return sprintf('%s:%s:metrics', $this->cachePrefix(), $service);
    }

    private function probeCounterKey(string $service): string
    {
        return sprintf('%s:%s:half_open_probes', $this->cachePrefix(), $service);
    }

    private function lockKey(string $service): string
    {
        return sprintf('%s:%s:lock', $this->cachePrefix(), $service);
    }

    private function cachePrefix(): string
    {
        return (string) (($this->config['cache']['prefix'] ?? 'sentinel'));
    }

    private function cacheStore(): mixed
    {
        $store = $this->config['cache']['store'] ?? null;

        return $this->cacheFactory->store($store);
    }

    private function isEnabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? true);
    }

    /**
     * @template T
     * @param  Closure():T  $callback
     * @return T
     */
    private function withLock(string $service, Closure $callback): mixed
    {
        $store = $this->cacheStore();
        if (! method_exists($store, 'lock')) {
            return $callback();
        }

        try {
            $lockTtl = (int) ($this->config['cache']['lock_ttl'] ?? 5);
            $lockWait = (int) ($this->config['cache']['lock_wait'] ?? 3);

            return $store->lock($this->lockKey($service), $lockTtl)->block($lockWait, $callback);
        } catch (Throwable) {
            return $callback();
        }
    }

    private function secondsUntil(int $defaultSeconds): int
    {
        return max(1, $defaultSeconds);
    }
}
