<?php

declare(strict_types=1);

return [
    'enabled' => env('SENTINEL_ENABLED', true),

    'cache' => [
        'store' => env('SENTINEL_CACHE_STORE'),
        'prefix' => env('SENTINEL_CACHE_PREFIX', 'sentinel'),
        'lock_ttl' => (int) env('SENTINEL_LOCK_TTL', 5),
        'lock_wait' => (int) env('SENTINEL_LOCK_WAIT', 3),
    ],

    'defaults' => [
        'threshold' => (float) env('SENTINEL_THRESHOLD', 50.0),
        'min_requests' => (int) env('SENTINEL_MIN_REQUESTS', 10),
        'cooldown_seconds' => (int) env('SENTINEL_COOLDOWN_SECONDS', 60),
        'release_delay_seconds' => (int) env('SENTINEL_RELEASE_DELAY_SECONDS', 30),
        'half_open_probe_limit' => (int) env('SENTINEL_HALF_OPEN_PROBE_LIMIT', 1),
        'peak_hours' => [
            'enabled' => false,
            'timezone' => env('SENTINEL_PEAK_HOURS_TIMEZONE', 'UTC'),
            'start' => env('SENTINEL_PEAK_HOURS_START', '08:00'),
            'end' => env('SENTINEL_PEAK_HOURS_END', '18:00'),
            'threshold' => (float) env('SENTINEL_PEAK_HOURS_THRESHOLD', 35.0),
        ],
    ],

    'failure_classifier' => [
        'class' => Wal3fo\LaravelSentinel\Services\DefaultFailureClassifier::class,
        'ignored_exceptions' => [
            Illuminate\Validation\ValidationException::class,
            DomainException::class,
        ],
    ],

    'status_endpoint' => [
        'enabled' => false,
        'path' => '/internal/sentinel/status',
        'middleware' => ['web', 'auth'],
    ],

    'services' => [
        // 'mail' => [
        //     'threshold' => 40.0,
        //     'min_requests' => 20,
        //     'cooldown_seconds' => 120,
        //     'release_delay_seconds' => 20,
        //     'half_open_probe_limit' => 1,
        // ],
    ],
];
