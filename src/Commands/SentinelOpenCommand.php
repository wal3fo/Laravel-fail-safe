<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Commands;

use Illuminate\Console\Command;
use Wal3fo\LaravelSentinel\SentinelManager;

final class SentinelOpenCommand extends Command
{
    protected $signature = 'sentinel:open {service : Service name to force open}';

    protected $description = 'Force a circuit to open state';

    public function handle(SentinelManager $sentinel): int
    {
        $service = (string) $this->argument('service');
        $sentinel->forceOpen($service);

        $this->info(sprintf('Circuit [%s] forced to open.', $service));

        return self::SUCCESS;
    }
}
