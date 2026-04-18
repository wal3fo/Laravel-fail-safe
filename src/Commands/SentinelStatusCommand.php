<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Commands;

use Illuminate\Console\Command;
use Wal3fo\LaravelSentinel\SentinelManager;

final class SentinelStatusCommand extends Command
{
    protected $signature = 'sentinel:status {service? : Specific service name}';

    protected $description = 'Display Sentinel circuit status';

    public function handle(SentinelManager $sentinel): int
    {
        $service = $this->argument('service');

        if (is_string($service)) {
            $status = $sentinel->status($service);
            $this->table(
                ['service', 'state', 'attempts', 'failures', 'failure_rate', 'opened_at', 'next_retry_at'],
                [[
                    $status['service'],
                    $status['state'],
                    $status['attempts'],
                    $status['failures'],
                    $status['failure_rate'],
                    $status['opened_at'],
                    $status['next_retry_at'],
                ]]
            );

            return self::SUCCESS;
        }

        $statuses = $sentinel->status();

        if ($statuses === []) {
            $this->warn('No services configured under sentinel.services.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($statuses as $status) {
            $rows[] = [
                $status['service'],
                $status['state'],
                $status['attempts'],
                $status['failures'],
                $status['failure_rate'],
                $status['opened_at'],
                $status['next_retry_at'],
            ];
        }

        $this->table(['service', 'state', 'attempts', 'failures', 'failure_rate', 'opened_at', 'next_retry_at'], $rows);

        return self::SUCCESS;
    }
}
