<?php

declare(strict_types=1);

namespace Wal3fo\LaravelSentinel\Tests\Feature;

use Wal3fo\LaravelSentinel\SentinelManager;
use Wal3fo\LaravelSentinel\Tests\TestCase;

final class SentinelCommandsTest extends TestCase
{
    public function test_open_close_and_reset_commands(): void
    {
        $this->artisan('sentinel:open mail')
            ->expectsOutput('Circuit [mail] forced to open.')
            ->assertSuccessful();

        $sentinel = $this->app->make(SentinelManager::class);
        $this->assertTrue($sentinel->isOpen('mail'));

        $this->artisan('sentinel:close mail')
            ->expectsOutput('Circuit [mail] forced to closed.')
            ->assertSuccessful();

        $this->assertFalse($sentinel->isOpen('mail'));

        $sentinel->recordFailure('mail', new \RuntimeException('x'));
        $this->artisan('sentinel:reset mail')
            ->expectsOutput('Circuit [mail] reset.')
            ->assertSuccessful();

        $status = $sentinel->status('mail');
        $this->assertSame(0, $status['attempts']);
        $this->assertSame(0, $status['failures']);
    }

    public function test_status_command_runs(): void
    {
        $this->artisan('sentinel:status')
            ->assertSuccessful();

        $this->artisan('sentinel:status mail')
            ->assertSuccessful();
    }
}
