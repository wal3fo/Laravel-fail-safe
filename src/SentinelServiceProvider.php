<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Wal3fo\LaravelSentinel\Commands\SentinelCloseCommand;
use Wal3fo\LaravelSentinel\Commands\SentinelOpenCommand;
use Wal3fo\LaravelSentinel\Commands\SentinelResetCommand;
use Wal3fo\LaravelSentinel\Commands\SentinelStatusCommand;
use Wal3fo\LaravelSentinel\Contracts\FailureClassifier;
use Wal3fo\LaravelSentinel\Support\Clock;
use Wal3fo\LaravelSentinel\Support\ServiceConfigResolver;
use Wal3fo\LaravelSentinel\Support\SystemClock;

final class SentinelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sentinel.php', 'sentinel');

        $this->app->singleton(Clock::class, SystemClock::class);
        $this->app->singleton(ServiceConfigResolver::class);

        $this->app->singleton(FailureClassifier::class, function ($app): FailureClassifier {
            $config = (array) $app['config']->get('sentinel.failure_classifier', []);
            $class = $config['class'] ?? \Wal3fo\LaravelSentinel\Services\DefaultFailureClassifier::class;

            if ($class === \Wal3fo\LaravelSentinel\Services\DefaultFailureClassifier::class) {
                return new $class((array) ($config['ignored_exceptions'] ?? []));
            }

            return $app->make($class);
        });

        $this->app->singleton(SentinelManager::class, function ($app): SentinelManager {
            return new SentinelManager(
                $app['cache'],
                $app['events'],
                $app->make(FailureClassifier::class),
                $app->make(Clock::class),
                $app->make(ServiceConfigResolver::class),
                (array) $app['config']->get('sentinel', [])
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/sentinel.php' => config_path('sentinel.php'),
        ], 'sentinel-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                SentinelStatusCommand::class,
                SentinelOpenCommand::class,
                SentinelCloseCommand::class,
                SentinelResetCommand::class,
            ]);
        }

        $statusEndpoint = (array) config('sentinel.status_endpoint', []);
        if (($statusEndpoint['enabled'] ?? false) !== true) {
            return;
        }

        Route::middleware((array) ($statusEndpoint['middleware'] ?? ['web', 'auth']))
            ->get((string) ($statusEndpoint['path'] ?? '/internal/sentinel/status'), function (SentinelManager $sentinel) {
                return response()->json($sentinel->status());
            });
    }
}
