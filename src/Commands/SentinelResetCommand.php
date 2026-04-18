<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Commands;

use Illuminate\Console\Command;
use Wal3fo\LaravelSentinel\SentinelManager;

final class SentinelResetCommand extends Command
{
    protected $signature = 'sentinel:reset {service? : Optional service name}';

    protected $description = 'Reset one or all circuits and counters';

    public function handle(SentinelManager $sentinel): int
    {
        $service = $this->argument('service');

        if (is_string($service)) {
            $sentinel->reset($service);
            $this->info(sprintf('Circuit [%s] reset.', $service));

            return self::SUCCESS;
        }

        $sentinel->reset();
        $this->info('All configured circuits reset.');

        return self::SUCCESS;
    }
}
