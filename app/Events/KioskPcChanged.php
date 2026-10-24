<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KioskPcChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $kioskId,
        public ?string $lastError,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin-kiosks')];
    }

    public function broadcastAs(): string
    {
        return 'KioskPcChanged';
    }

    public function broadcastWith(): array
    {
        return [
            'kiosk_id' => $this->kioskId,
            'last_error' => $this->lastError,
        ];
    }
}
