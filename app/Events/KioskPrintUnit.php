<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class KioskPrintUnit implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array{unit_id: string, job_id: string, kiosk_id: string, duplex: bool, url: string}  $payload
     */
    public function __construct(public array $payload) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('kiosk-pc.'.$this->payload['kiosk_id'])];
    }

    public function broadcastAs(): string
    {
        return 'KioskPrintUnit';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
