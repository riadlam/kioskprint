<?php

namespace App\Services\Print;

use App\Events\KioskPcChanged;
use App\Events\KioskPrintUnit as KioskPrintUnitEvent;
use App\Events\PrintJobCompleted;
use App\Events\PrintJobFailed;
use App\Events\PrintJobProgress;
use App\Models\KioskPc;
use App\Models\KioskPrintUnit;
use App\Models\PrintJob;
use App\Models\PrintSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

class KioskPrintRelay
{
    /**
     * @param  list<array{path: string, page_ids: list<int>}>  $units
     */
    public function queue(PrintJob $job, array $units): void
    {
        $rows = [];

        foreach ($units as $index => $unit) {
            $rows[] = KioskPrintUnit::query()->create([
                'print_job_id' => $job->id,
                'kiosk_id' => (string) $job->kiosk_id,
                'position' => $index + 1,
                'page_ids' => $unit['page_ids'],
                'path' => $this->relative($unit['path']),
                'duplex' => (bool) $job->duplex,
                'state' => 'pending',
            ]);
        }

        foreach ($rows as $row) {
            $this->push($row);
        }
    }

    public function releasePending(string $kioskId): void
    {
        KioskPrintUnit::query()
            ->select('kiosk_print_units.*')
            ->join('print_jobs', 'print_jobs.id', '=', 'kiosk_print_units.print_job_id')
            ->where('kiosk_print_units.kiosk_id', $kioskId)
            ->where('kiosk_print_units.state', 'pending')
            ->orderBy('print_jobs.created_at')
            ->orderBy('kiosk_print_units.position')
            ->get()
            ->each(fn (KioskPrintUnit $unit) => $this->push($unit));
    }

    /**
     * @return array{status: string}
     */
    public function finish(KioskPrintUnit $unit, string $kioskId, bool $ok, string $error): array
    {
        $result = DB::transaction(function () use ($unit, $kioskId, $ok, $error) {
            $locked = KioskPrintUnit::query()->whereKey($unit->id)->lockForUpdate()->first();

            if (! $locked || $locked->kiosk_id !== $kioskId) {
                return ['status' => 'missing', 'events' => []];
            }

            if ($locked->state !== 'pending') {
                return ['status' => $locked->state, 'events' => []];
            }

            $job = PrintJob::query()->whereKey($locked->print_job_id)->lockForUpdate()->first();

            if (! $job) {
                return ['status' => 'missing', 'events' => []];
            }

            if (! $ok) {
                $message = $error !== '' ? $error : 'The printer rejected the document.';
                $locked->update(['state' => 'failed', 'error' => $message]);
                KioskPrintUnit::query()
                    ->where('print_job_id', $job->id)
                    ->where('state', 'pending')
                    ->update(['state' => 'failed', 'error' => 'stopped']);
                $job->update(['status' => 'failed']);
                $this->rememberError($locked->kiosk_id, $message);

                return [
                    'status' => 'failed',
                    'events' => [
                        new PrintJobFailed($job->fresh()),
                        new KioskPcChanged($locked->kiosk_id, $message),
                    ],
                    'spool' => 'print-spool/'.$job->id,
                ];
            }

            $locked->update(['state' => 'printed', 'error' => null]);
            $printed = (int) KioskPrintUnit::query()
                ->where('print_job_id', $job->id)
                ->where('state', 'printed')
                ->get()
                ->sum(fn (KioskPrintUnit $row) => count($row->page_ids ?? []));
            $job->update(['printed_pages' => $printed]);
            $events = [new PrintJobProgress($job->fresh(), $printed, (int) $job->page_count)];
            $spool = null;

            $waiting = KioskPrintUnit::query()
                ->where('print_job_id', $job->id)
                ->where('state', 'pending')
                ->exists();

            if (! $waiting) {
                $job->update([
                    'status' => 'printed',
                    'printed_pages' => (int) $job->page_count,
                ]);
                $this->rememberError($locked->kiosk_id, null);
                $events[] = new PrintJobCompleted($job->fresh());
                $events[] = new KioskPcChanged($locked->kiosk_id, null);
                $spool = 'print-spool/'.$job->id;
            }

            return ['status' => 'printed', 'events' => $events, 'spool' => $spool];
        });

        if (($result['status'] ?? '') === 'missing') {
            abort(404);
        }

        foreach ($result['events'] as $event) {
            $this->broadcastSafely($event);
        }

        if (! empty($result['spool'])) {
            Storage::disk('local')->deleteDirectory($result['spool']);
        }

        return ['status' => $result['status']];
    }

    public function push(KioskPrintUnit $unit): void
    {
        if ($unit->state !== 'pending') {
            return;
        }

        $this->broadcastSafely(new KioskPrintUnitEvent([
            'unit_id' => $unit->id,
            'job_id' => $unit->print_job_id,
            'kiosk_id' => $unit->kiosk_id,
            'duplex' => (bool) $unit->duplex,
            'toner_save' => PrintSetting::current()->toner_save,
            'url' => URL::temporarySignedRoute('kiosk.units.file', now()->addMinutes(10), [
                'kiosk' => $unit->kiosk_id,
                'unit' => $unit->id,
            ]),
        ]));
    }

    private function rememberError(string $kioskId, ?string $error): void
    {
        KioskPc::query()->updateOrCreate(
            ['kiosk_id' => $kioskId],
            [
                'last_error' => $error,
                'last_error_at' => $error === null ? null : now(),
            ],
        );
    }

    private function relative(string $absolute): string
    {
        $root = rtrim(str_replace('\\', '/', Storage::disk('local')->path('')), '/');
        $path = str_replace('\\', '/', $absolute);

        if (! str_starts_with($path, $root)) {
            throw new RuntimeException('Print file is outside storage.');
        }

        return ltrim(substr($path, strlen($root)), '/');
    }

    private function broadcastSafely(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
