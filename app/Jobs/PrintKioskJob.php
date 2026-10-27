<?php

namespace App\Jobs;

use App\Events\PrintJobCompleted;
use App\Events\PrintJobFailed;
use App\Events\PrintJobProgress;
use App\Models\PrintJob;
use App\Models\PrintPage;
use App\Services\Print\KioskPrinter;
use App\Services\Print\KioskPrintRelay;
use App\Services\Print\PrintUnitComposer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class PrintKioskJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public int $uniqueFor = 3600;

    public function __construct(public string $printJobId) {}

    public function uniqueId(): string
    {
        return $this->printJobId;
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->printJobId))->expireAfter(600)];
    }

    public function handle(PrintUnitComposer $composer, KioskPrinter $printer): void
    {
        if (config('print.driver') === 'socket') {
            $this->handOff($composer);

            return;
        }

        $job = $this->claim();

        if (! $job) {
            return;
        }

        $folder = 'print-spool/'.$job->id;

        try {
            $pages = $this->orderedPages($job);
            $units = $composer->compose($job->id, $pages, (bool) $job->duplex);
            $sent = 0;
            $total = count($pages);

            foreach ($units as $unit) {
                $printer->send(
                    $unit['path'],
                    (bool) $job->duplex,
                    $unit['page_ids'],
                    function () use ($job, $unit, &$sent, $total) {
                        $sent += count($unit['page_ids']);
                        $job->forceFill(['printed_pages' => $sent])->save();
                        $this->broadcastSafely(new PrintJobProgress($job, $sent, $total));
                    },
                );
            }

            $job->update(['status' => 'printed', 'printed_pages' => $total]);
            $this->broadcastSafely(new PrintJobCompleted($job->fresh()));
        } catch (Throwable $exception) {
            $job->update(['status' => 'failed']);
            $this->broadcastSafely(new PrintJobFailed($job->fresh()));
            report($exception);
        } finally {
            Storage::disk('local')->deleteDirectory($folder);
        }
    }

    private function handOff(PrintUnitComposer $composer): void
    {
        $job = $this->claim();

        if (! $job) {
            return;
        }

        try {
            $pages = $this->orderedPages($job);
            $units = $composer->compose($job->id, $pages, (bool) $job->duplex);
            app(KioskPrintRelay::class)->queue($job, $units);
        } catch (Throwable $exception) {
            $job->update(['status' => 'failed']);
            $this->broadcastSafely(new PrintJobFailed($job->fresh()));
            Storage::disk('local')->deleteDirectory('print-spool/'.$job->id);
            report($exception);
        }
    }

    private function broadcastSafely(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function claim(): ?PrintJob
    {
        return DB::transaction(function () {
            $job = PrintJob::query()->whereKey($this->printJobId)->lockForUpdate()->first();

            if (! $job || $job->status !== 'paid' || ! $job->kiosk_id) {
                return null;
            }

            $job->update(['status' => 'printing']);

            return $job;
        });
    }

    /**
     * @return list<array{id: int, absolute_path: string, mime: string, number: int}>
     */
    private function orderedPages(PrintJob $job): array
    {
        $ids = array_map('intval', $job->print_manifest['page_ids'] ?? []);

        if ($ids === []) {
            throw new \RuntimeException('Print order was not saved.');
        }

        $pages = PrintPage::query()
            ->with('document')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (PrintPage $page) => (int) $page->id);

        $ordered = [];

        foreach ($ids as $id) {
            $page = $pages->get($id);
            $document = $page?->document;

            if (! $page || ! $document) {
                throw new \RuntimeException('A saved page is missing.');
            }

            $absolute = Storage::disk('local')->path($document->path);

            if (! is_file($absolute)) {
                throw new \RuntimeException('A saved file is missing.');
            }

            $sheet = [
                'id' => (int) $page->id,
                'absolute_path' => $absolute,
                'mime' => (string) $document->mime_type,
                'number' => (int) $page->number,
            ];
            $copies = max(1, (int) ($page->copies ?? 1));

            for ($copy = 0; $copy < $copies; $copy++) {
                $ordered[] = $sheet;
            }
        }

        return $ordered;
    }
}
