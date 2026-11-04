<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobPaymentProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PrintJob $job) {}

    public function broadcastOn(): array
    {
        return [new Channel('kiosk.'.$this->job->kiosk_id)];
    }

    public function broadcastAs(): string
    {
        return 'PrintJobPaymentProgress';
    }

    public function broadcastWith(): array
    {
        // Prefer fresh row so phone/tablet never paint a stale inserted amount.
        $job = $this->job->fresh() ?? $this->job;
        $required = (int) round((float) $job->amount);
        $inserted = (int) round((float) $job->amount_inserted);

        return [
            'job_id' => $job->id,
            'kiosk_id' => $job->kiosk_id,
            'required' => $required,
            'inserted' => $inserted,
            'remaining' => max(0, $required - $inserted),
            'payment_method' => $job->payment_method,
            'status' => $job->status,
            'page_count' => (int) $job->page_count,
            'amount' => $required,
            'at' => (int) floor(microtime(true) * 1000),
        ];
    }
}
