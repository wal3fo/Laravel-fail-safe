<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Commands;

use Illuminate\Console\Command;
use Wal3fo\LaravelSentinel\SentinelManager;

final class SentinelCloseCommand extends Command
{
    protected $signature = 'sentinel:close {service : Service name to force close}';

    protected $description = 'Force a circuit to closed state';

    public function handle(SentinelManager $sentinel): int
    {
        $service = (string) $this->argument('service');
        $sentinel->forceClose($service);

        $this->info(sprintf('Circuit [%s] forced to closed.', $service));

        return self::SUCCESS;
    }
}
