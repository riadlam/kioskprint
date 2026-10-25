<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KioskDeskRotated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $kioskId) {}

    public function broadcastOn(): array
    {
        return [new Channel('kiosk.'.$this->kioskId)];
    }

    public function broadcastAs(): string
    {
        return 'KioskDeskRotated';
    }

    public function broadcastWith(): array
    {
        return ['kiosk_id' => $this->kioskId];
    }
}
