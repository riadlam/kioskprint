<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ScreenUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): array
    {
        return [new Channel('kiosk-screen')];
    }

    public function broadcastAs(): string
    {
        return 'ScreenUpdated';
    }

    public function broadcastWith(): array
    {
        return [
            'updated_at' => now()->toIso8601String(),
        ];
    }
}
