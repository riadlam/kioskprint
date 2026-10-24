<?php

namespace App\Services\Print;

use App\Events\PrintJobPaid;
use App\Events\PrintJobPaymentProgress;
use App\Jobs\PrintKioskJob;
use App\Models\CoinEvent;
use App\Models\PrintJob;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Throwable;

class CoinCreditService
{
    /** Pulse count from the HX-916 to whole dinars. Anything else is ignored. */
    public const DINARS = [
        1 => 5,
        2 => 10,
        3 => 20,
        4 => 50,
        5 => 100,
        6 => 200,
    ];

    /**
     * Credit the kiosk's open cash job. The same event_key never adds twice.
     *
     * @return array<string, mixed>
     */
    public function credit(string $kioskId, int $pulses, string $eventKey): array
    {
        $dinars = self::DINARS[$pulses] ?? null;

        if ($dinars === null) {
            return $this->emptyView('ignored');
        }

        return $this->apply($kioskId, null, $pulses, $dinars, $eventKey);
    }

    /**
     * Add an exact dinar amount (used by the demo clock). Still idempotent.
     *
     * @return array<string, mixed>
     */
    public function addDinars(PrintJob $job, int $dinars, string $eventKey): array
    {
        if ($dinars < 1 || $job->kiosk_id === null) {
            return $this->view($job->fresh(), 'ignored');
        }

        return $this->apply((string) $job->kiosk_id, $job->id, 0, $dinars, $eventKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function view(?PrintJob $job, ?string $result = null): array
    {
        $required = $job ? $this->due($job) : 0;
        $inserted = $job ? $this->inserted($job) : 0;
        $remaining = max(0, $required - $inserted);
        $complete = $job && in_array($job->status, ['paid', 'printing', 'printed'], true);

        $payload = [
            'job_id' => $job?->id,
            'payment_method' => $job?->payment_method,
            'status' => $job?->status,
            'required' => $required,
            'inserted' => $inserted,
            'remaining' => $remaining,
            'complete' => $complete,
            'progress' => $required > 0 ? min(1, round($inserted / $required, 4)) : 0,
        ];

        if ($result !== null) {
            $payload['result'] = $result;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function apply(string $kioskId, ?string $onlyJobId, int $pulses, int $dinars, string $eventKey): array
    {
        try {
            $outcome = DB::transaction(function () use ($kioskId, $onlyJobId, $pulses, $dinars, $eventKey) {
                $existing = CoinEvent::query()->where('event_key', $eventKey)->lockForUpdate()->first();

                if ($existing) {
                    return ['kind' => 'replay', 'job_id' => $existing->print_job_id];
                }

                $query = PrintJob::query()
                    ->where('kiosk_id', $kioskId)
                    ->where('status', 'awaiting_payment')
                    ->where('payment_method', 'monnayeur')
                    ->orderBy('created_at')
                    ->lockForUpdate();

                if ($onlyJobId !== null) {
                    $query->whereKey($onlyJobId);
                }

                $job = $query->first();

                if (! $job) {
                    CoinEvent::query()->create([
                        'print_job_id' => null,
                        'kiosk_id' => $kioskId,
                        'pulses' => $pulses,
                        'dinars' => $dinars,
                        'event_key' => $eventKey,
                    ]);

                    return ['kind' => 'ignored', 'job_id' => null];
                }

                $required = $this->due($job);
                $inserted = $this->inserted($job) + $dinars;

                CoinEvent::query()->create([
                    'print_job_id' => $job->id,
                    'kiosk_id' => $kioskId,
                    'pulses' => $pulses,
                    'dinars' => $dinars,
                    'event_key' => $eventKey,
                ]);

                if ($required > 0 && $inserted >= $required) {
                    $job->forceFill([
                        'amount_inserted' => $inserted,
                        'status' => 'paid',
                        'paid_at' => now(),
                    ])->save();

                    return ['kind' => 'paid', 'job_id' => $job->id];
                }

                $job->forceFill(['amount_inserted' => $inserted])->save();

                return ['kind' => 'credited', 'job_id' => $job->id];
            });
        } catch (UniqueConstraintViolationException) {
            $existing = CoinEvent::query()->where('event_key', $eventKey)->first();

            return $this->view(
                $existing?->print_job_id ? PrintJob::query()->find($existing->print_job_id) : null,
                'replay',
            );
        }

        $job = $outcome['job_id'] ? PrintJob::query()->find($outcome['job_id']) : null;

        if ($outcome['kind'] === 'credited' && $job) {
            $this->broadcastSafely(new PrintJobPaymentProgress($job));
        }

        if ($outcome['kind'] === 'paid' && $job) {
            $this->broadcastSafely(new PrintJobPaid($job));
            PrintKioskJob::dispatch($job->id);
        }

        return $this->view($job?->fresh(), $outcome['kind']);
    }

    private function broadcastSafely(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyView(string $result): array
    {
        return $this->view(null, $result);
    }

    private function due(PrintJob $job): int
    {
        return (int) round((float) $job->amount);
    }

    private function inserted(PrintJob $job): int
    {
        return (int) round((float) $job->amount_inserted);
    }
}
