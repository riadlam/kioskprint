<?php

namespace App\Jobs;

use App\Models\PrintJob;
use App\Services\Print\CoinCreditService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

        $seconds = max(5, (int) config('print.coin_hold_seconds', 20));

        if ($job->updated_at !== null && $job->updated_at->gt(now()->subSeconds($seconds))) {
            return;
        }

        app(CoinCreditService::class)->abandon($job);
    }
}
