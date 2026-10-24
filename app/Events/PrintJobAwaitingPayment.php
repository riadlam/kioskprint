<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobAwaitingPayment implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PrintJob $job) {}

    public function broadcastOn(): array
    {
        return [new Channel('kiosk.'.$this->job->kiosk_id)];
    }

    public function broadcastAs(): string
    {
        return 'PrintJobAwaitingPayment';
    }

    public function broadcastWith(): array
    {
        $required = (int) round((float) $this->job->amount);

        return [
            'job_id' => $this->job->id,
            'kiosk_id' => $this->job->kiosk_id,
            'required' => $required,
            'inserted' => 0,
            'remaining' => $required,
            'payment_method' => $this->job->payment_method,
            'status' => $this->job->status,
            'page_count' => (int) $this->job->page_count,
            'amount' => $required,
        ];
    }
}
