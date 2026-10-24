<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobCompleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PrintJob $job) {}

    public function broadcastOn(): array
    {
        return [new Channel('kiosk.'.$this->job->kiosk_id)];
    }

    public function broadcastAs(): string
    {
        return 'PrintJobCompleted';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->job->id,
            'kiosk_id' => $this->job->kiosk_id,
            'success' => true,
            'page_count' => (int) $this->job->page_count,
            'amount' => (float) $this->job->amount,
            'status' => $this->job->status,
        ];
    }
}
