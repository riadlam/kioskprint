<?php

namespace App\Events;

use App\Models\PrintJob;
use App\Services\Print\PrintPricing;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobPaid implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PrintJob $job) {}

    public function broadcastOn(): array
    {
        return [new Channel('kiosk.'.$this->job->kiosk_id)];
    }

    public function broadcastAs(): string
    {
        return 'PrintJobPaid';
    }

    public function broadcastWith(): array
    {
        $quote = app(PrintPricing::class)->quote($this->job);

        return [
            'job_id' => $this->job->id,
            'kiosk_id' => $this->job->kiosk_id,
            'page_count' => (int) $this->job->page_count,
            'amount' => (float) $this->job->amount,
            'formatted' => $quote['formatted'],
            'duplex' => (bool) $this->job->duplex,
            'payment_method' => $this->job->payment_method,
            'status' => $this->job->status,
        ];
    }
}
