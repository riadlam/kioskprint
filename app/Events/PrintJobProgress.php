<?php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public PrintJob $job,
        public int $currentPage,
        public int $totalPages,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('kiosk.'.$this->job->kiosk_id)];
    }

    public function broadcastAs(): string
    {
        return 'PrintJobProgress';
    }

    public function broadcastWith(): array
    {
        $total = max(1, $this->totalPages);

        return [
            'job_id' => $this->job->id,
            'kiosk_id' => $this->job->kiosk_id,
            'current_page' => $this->currentPage,
            'total_pages' => $this->totalPages,
            'percent' => (int) round(($this->currentPage / $total) * 100),
            'amount' => (float) $this->job->amount,
        ];
    }
}
