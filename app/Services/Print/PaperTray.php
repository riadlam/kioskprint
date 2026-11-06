<?php

namespace App\Services\Print;

use App\Models\PaperStock;
use App\Models\PaperUse;
use App\Models\PrintJob;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PaperTray
{
    public const LOW_AT = 5;

    public const MAX_ADD = 5000;

    /**
     * @return array<string, mixed>
     */
    public function add(int $sheets): array
    {
        return DB::transaction(function () use ($sheets) {
            $stock = $this->locked();
            $stock->sheets_left = (int) $stock->sheets_left + $sheets;
            $stock->filled_sheets = (int) $stock->sheets_left;
            $stock->filled_at = now();
            $stock->low_alerted_at = null;
            $stock->save();

            return $this->snapshot($stock);
        });
    }

    /**
     * One physical sheet left the Canon. Returns the stack summary only when
     * this sheet is the one that drops the tray under five, so the warning
     * is sent once per refill.
     *
     * @return array<string, mixed>|null
     */
    public function consume(PrintJob $job, int $sheets = 1): ?array
    {
        $sheets = max(1, $sheets);

        return DB::transaction(function () use ($job, $sheets) {
            $stock = $this->locked();
            $before = (int) $stock->sheets_left;
            $stock->sheets_left = max(0, $before - $sheets);
            $crossed = $stock->filled_at !== null
                && $stock->low_alerted_at === null
                && $before >= self::LOW_AT
                && (int) $stock->sheets_left < self::LOW_AT;

            if ($crossed) {
                $stock->low_alerted_at = now();
            }

            $stock->save();
            PaperUse::query()->create([
                'print_job_id' => $job->id,
                'sheets' => $sheets,
            ]);

            return $crossed ? $this->snapshot($stock->fresh() ?? $stock) : null;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        return $this->snapshot(PaperStock::current());
    }

    private function locked(): PaperStock
    {
        $stock = PaperStock::query()->orderBy('id')->lockForUpdate()->first();

        if ($stock) {
            return $stock;
        }

        PaperStock::current();

        return PaperStock::query()->orderBy('id')->lockForUpdate()->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(PaperStock $stock): array
    {
        $used = 0;
        $jobs = 0;

        if ($stock->filled_at) {
            $used = (int) PaperUse::query()
                ->where('created_at', '>=', $stock->filled_at)
                ->sum('sheets');
            $jobs = (int) PaperUse::query()
                ->where('created_at', '>=', $stock->filled_at)
                ->distinct()
                ->count('print_job_id');
        }

        return [
            'left' => (int) $stock->sheets_left,
            'filled_sheets' => (int) $stock->filled_sheets,
            'filled_at' => $stock->filled_at,
            'used' => $used,
            'jobs' => $jobs,
            'lasted' => $this->lasted($stock->filled_at),
        ];
    }

    private function lasted(?CarbonInterface $from): string
    {
        if (! $from) {
            return 'not started';
        }

        return $from->diffForHumans(now(), [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            'parts' => 2,
            'join' => true,
        ]);
    }
}
