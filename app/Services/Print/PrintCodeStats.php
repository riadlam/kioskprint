<?php

namespace App\Services\Print;

use App\Models\PrintCode;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;

class PrintCodeStats
{
    public function markPrinted(PrintJob $job): void
    {
        if (! $job->print_code_id) {
            return;
        }

        DB::transaction(function () use ($job) {
            $code = PrintCode::query()->whereKey($job->print_code_id)->lockForUpdate()->first();

            if (! $code) {
                return;
            }

            // Count only once per job when it first becomes printed.
            $already = (int) PrintJob::query()
                ->where('print_code_id', $code->id)
                ->where('status', 'printed')
                ->whereKeyNot($job->id)
                ->count();

            $total = $already + 1;
            if ((int) $code->print_count !== $total) {
                $code->forceFill(['print_count' => $total])->save();
            }
        });
    }
}
