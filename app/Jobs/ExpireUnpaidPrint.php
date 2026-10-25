<?php

namespace App\Jobs;

use App\Events\PrintJobFailed;
use App\Models\PrintJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExpireUnpaidPrint implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $jobId) {}

    public function handle(): void
    {
        $job = PrintJob::query()->find($this->jobId);

        if (! $job || $job->status !== 'awaiting_payment' || $job->payment_method !== 'monnayeur') {
            return;
        }

        if ((float) $job->amount_inserted > 0) {
            return;
        }

        if ($job->updated_at !== null && $job->updated_at->gt(now()->subSeconds(90))) {
            return;
        }

        $job->update(['status' => 'failed']);

        try {
            broadcast(new PrintJobFailed($job));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
