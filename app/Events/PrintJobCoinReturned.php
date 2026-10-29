<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobCoinReturned implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public PrintJob $job, public int $coin) {}

    public function broadcastOn(): array
    {
        return [new Channel('kiosk.'.$this->job->kiosk_id)];
    }

    public function broadcastAs(): string
    {
        return 'PrintJobCoinReturned';
    }

    public function broadcastWith(): array
    {
        $required = (int) round((float) $this->job->amount);
        $inserted = (int) round((float) $this->job->amount_inserted);

        return [
            'job_id' => $this->job->id,
            'kiosk_id' => $this->job->kiosk_id,
            'coin' => $this->coin,
            'required' => $required,
            'inserted' => $inserted,
            'remaining' => max(0, $required - $inserted),
            'payment_method' => $this->job->payment_method,
            'status' => $this->job->status,
        ];
    }
}
