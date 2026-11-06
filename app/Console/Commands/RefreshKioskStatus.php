<?php

namespace App\Console\Commands;

use App\Services\Telegram\StatusPublisher;
use Illuminate\Console\Command;

class RefreshKioskStatus extends Command
{
    protected $signature = 'kiosk:watch';

    protected $description = 'Edit the private Telegram status note';

    public function handle(StatusPublisher $publisher): int
    {
        $publisher->refresh();

        return self::SUCCESS;
    }
}
