<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskPrintStatusController extends Controller
{
    public function __invoke(Request $request, string $kiosk, PrintJob $job): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json(null, 204, $this->cors());
        }

        abort_unless($job->kiosk_id === $kiosk, 404);

        $this->failIfStalled($job);
        $job->refresh();

        $total = max(
            (int) $job->page_count,
            count($job->print_manifest['page_ids'] ?? []),
        );
        $current = (int) $job->printed_pages;

        if ($job->status === 'printed') {
            $current = max($current, $total);
        }

        $percent = $total > 0 ? (int) round((min($current, $total) / $total) * 100) : 0;
        $required = (int) round((float) $job->amount);
        $inserted = (int) round((float) $job->amount_inserted);

        return response()->json([
            'job_id' => $job->id,
            'kiosk_id' => $job->kiosk_id,
            'status' => $job->status,
            'current_page' => $current,
            'total_pages' => $total,
            'page_count' => (int) $job->page_count,
            'percent' => $job->status === 'printed' ? 100 : $percent,
            'amount' => $required,
            'payment_method' => $job->payment_method,
            'required' => $required,
            'amount_inserted' => $inserted,
            'remaining' => max(0, $required - $inserted),
        ], 200, $this->cors());
    }

    private function failIfStalled(PrintJob $job): void
    {
        if ($job->status !== 'printing' || $job->updated_at === null) {
            return;
        }

        $limit = max(20, (int) config('print.page_timeout')) + 25;

        if ($job->updated_at->lt(now()->subSeconds($limit))) {
            $job->update(['status' => 'failed']);
        }
    }

    /**
     * @return array<string, string>
     */
    private function cors(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Accept, Content-Type, Access-Control-Request-Private-Network',
            'Access-Control-Allow-Private-Network' => 'true',
            'Access-Control-Max-Age' => '600',
        ];
    }
}
