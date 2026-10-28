<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobFailed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PrintJob $job, public string $reason = '') {}

    public function broadcastOn(): array
    {
        return [new Channel('kiosk.'.$this->job->kiosk_id)];
    }

    public function broadcastAs(): string
    {
        return 'PrintJobFailed';
    }

    public function broadcastWith(): array
    {
        return [
            'job_id' => $this->job->id,
            'kiosk_id' => $this->job->kiosk_id,
            'success' => false,
            'page_count' => (int) $this->job->page_count,
            'status' => $this->job->status,
            'reason' => $this->reason,
        ];
    }
}
