# Wal3fo Laravel Sentinel

[![Packagist Version](https://img.shields.io/packagist/v/wal3fo/laravel-sentinel?style=flat-square&color=6ee7b7&labelColor=0d1117)](https://packagist.org/packages/wal3fo/laravel-sentinel)
[![Total Downloads](https://img.shields.io/packagist/dt/wal3fo/laravel-sentinel?style=flat-square&color=818cf8&labelColor=0d1117)](https://packagist.org/packages/wal3fo/laravel-sentinel)
[![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&labelColor=0d1117)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-10+-FF2D20?style=flat-square&labelColor=0d1117)](https://laravel.com)
[![License](https://img.shields.io/packagist/l/wal3fo/laravel-sentinel?style=flat-square&color=fb923c&labelColor=0d1117)](LICENSE)

Production-ready circuit breaker for Laravel queues and service calls, built to stop cascading failures with closed, open, and half-open states.

## Introduction

When a dependency starts failing (mail API, payment gateway, CRM, webhook target), retries alone can overload workers and amplify outages. A circuit breaker prevents that by temporarily short-circuiting failing paths, then probing recovery in a controlled way.

Wal3fo Laravel Sentinel is designed for Laravel queue-heavy applications where resilience and predictable failure handling matter. Use it when:

- jobs call unstable or rate-limited external services
- multiple workers can overwhelm a failing dependency
- you need controlled backoff and safe recovery behavior
- you want observable state transitions and operational commands

## Features

- Queue middleware for automatic circuit checks and job release behavior
- Full circuit lifecycle: `closed`, `open`, `half_open`
- Cache-backed state and counters (Redis strongly recommended)
- Per-service configuration overrides
- Pluggable failure classification via contract
- Artisan commands for status and manual control
- Transition events for monitoring and alerting
- Laravel `10`, `11`, `12`, and `13` support

## Installation

```bash
composer require wal3fo/laravel-sentinel
```

Publish the package configuration:

```bash
php artisan vendor:publish --tag=sentinel-config
```

> [!TIP]
> Use Redis as the cache backend for distributed queue workers and reliable lock behavior.

## Configuration

Sentinel is configured in `config/sentinel.php`.

Global defaults are defined under `defaults`, then selectively overridden per service under `services`.

```php
<?php

return [
    'defaults' => [
        'threshold' => 50.0,
        'min_requests' => 10,
        'cooldown_seconds' => 60,
        'release_delay_seconds' => 30,
        'half_open_probe_limit' => 1,
    ],

    'services' => [
        'mail' => [
            'threshold' => 40.0,
            'min_requests' => 20,
            'cooldown_seconds' => 120,
            'release_delay_seconds' => 20,
            'half_open_probe_limit' => 1,
        ],

        'crm' => [
            'threshold' => 30.0,
            'min_requests' => 8,
            'cooldown_seconds' => 45,
            'release_delay_seconds' => 15,
        ],
    ],
];
```

### Key Options

- `threshold`: failure-rate percentage that opens the circuit
- `min_requests`: minimum attempts required before threshold evaluation
- `cooldown_seconds`: time to keep the circuit open before half-open probe window
- `release_delay_seconds`: queue job release delay when circuit is not allowing execution

### Per-Service Overrides

Any service key in `services` inherits from `defaults` and can override only what it needs.

## Basic Usage

### Queue Job Middleware

```php
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Wal3fo\LaravelSentinel\Middleware\CircuitBreakerMiddleware;

final class SendEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function middleware(): array
    {
        return [
            new CircuitBreakerMiddleware('mail'),
        ];
    }

    public function handle(): void
    {
        // Call external provider here.
    }
}
```

Multiple services in one job:

```php
public function middleware(): array
{
    return [
        new CircuitBreakerMiddleware(['mail', 'crm']),
    ];
}
```

### Attribute Usage

When you prefer declarative service mapping, use the package attribute and instantiate middleware without arguments.

```php
<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Wal3fo\LaravelSentinel\Attributes\UseCircuitBreaker;
use Wal3fo\LaravelSentinel\Middleware\CircuitBreakerMiddleware;

#[UseCircuitBreaker(['mail'])]
final class SendNotificationJob implements ShouldQueue
{
    public function middleware(): array
    {
        return [new CircuitBreakerMiddleware()];
    }

    public function handle(): void
    {
        // Perform work.
    }
}
```

## How It Works

Sentinel tracks attempts and failures per service in cache.

1. `closed`: requests/jobs flow normally while failures are recorded.
2. `open`: once `min_requests` is met and `failure_rate >= threshold`, executions are short-circuited.
3. `half_open`: after `cooldown_seconds`, Sentinel allows limited probes (`half_open_probe_limit`).
4. On probe success, the circuit closes and counters reset.
5. On probe failure, the circuit re-opens and cooldown restarts.

Failure rate is calculated as:

$$
  ext{failure_rate} = \frac{\text{failures}}{\text{attempts}} \times 100
$$

When a queue middleware check finds a circuit open (or half-open without an available probe slot), the job is released using the configured `release_delay_seconds`.

## Failure Classification

By default, Sentinel uses:

- `Wal3fo\LaravelSentinel\Services\DefaultFailureClassifier`

The default classifier ignores configured business exceptions and counts outage-like exceptions.

To customize behavior, provide your own classifier implementing:

- `Wal3fo\LaravelSentinel\Contracts\FailureClassifier`

```php
<?php

namespace App\Support;

use Throwable;
use Wal3fo\LaravelSentinel\Contracts\FailureClassifier;

final class ApiFailureClassifier implements FailureClassifier
{
    public function shouldCount(Throwable $throwable, string $service, array $serviceConfig = []): bool
    {
        return ! $throwable instanceof \DomainException;
    }
}
```

Register it in configuration:

```php
'failure_classifier' => [
    'class' => App\Support\ApiFailureClassifier::class,
],
```

## Artisan Commands

- `php artisan sentinel:status`
  Shows status for all configured services.
- `php artisan sentinel:status mail`
  Shows detailed status for a single service.
- `php artisan sentinel:open mail`
  Forces a service circuit to `open`.
- `php artisan sentinel:close mail`
  Forces a service circuit to `closed`.
- `php artisan sentinel:reset`
  Resets all configured service circuits and counters.
- `php artisan sentinel:reset mail`
  Resets one service circuit and counters.

## Events

Sentinel dispatches events on state changes:

- `Wal3fo\LaravelSentinel\Events\CircuitOpened`
  Fired when a circuit transitions to `open`.
- `Wal3fo\LaravelSentinel\Events\CircuitHalfOpened`
  Fired when cooldown expires and circuit enters `half_open`.
- `Wal3fo\LaravelSentinel\Events\CircuitClosed`
  Fired when recovery succeeds and circuit returns to `closed`.

Each event extends `Wal3fo\LaravelSentinel\Events\CircuitStateChanged` and includes `service`, `state`, and `status` data.

## Best Practices

- Apply circuit breakers to network or infrastructure-dependent operations, not pure business validation logic.
- Count failures that indicate dependency outage/timeouts; ignore business/domain exceptions where appropriate.
- Use Redis for cache and locks in multi-worker production environments.
- Set `min_requests` high enough to avoid opening on low-volume noise.
- Tune `threshold` per dependency based on real-world error patterns and SLOs.

## Testing

Run the test suite:

```bash
composer test
```

The package includes coverage for:

- middleware behavior and release flow when circuits are open
- state transitions (`closed` → `open` → `half_open` → `closed`)
- half-open probe limits
- failure classification rules
- event dispatching
- artisan command behavior

## Compatibility

- Laravel: `10`, `11`, `12`, `13`
- PHP: `^8.1` (effective minimum follows the selected Laravel version)

## Contributing

Contributions are welcome.

1. Fork the repository.
2. Create a feature branch.
3. Add tests for behavioral changes.
4. Run `composer test`.
5. Open a pull request with a clear description.

## License

Released under the MIT License.
