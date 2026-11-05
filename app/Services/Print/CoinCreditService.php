<?php

namespace App\Services\Print;

use App\Events\KioskCoinSeen;
use App\Events\PrintJobFailed;
use App\Events\PrintJobPaid;
use App\Events\PrintJobPaymentProgress;
use App\Jobs\ExpireUnpaidPrint;
use App\Jobs\PrintKioskJob;
use App\Models\CoinEvent;
use App\Models\PrintJob;
use App\Services\Telegram\OrderNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
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
    public static function maxPulses(int $remaining): int
    {
        $max = 0;

        foreach (self::DINARS as $pulses => $dinars) {
            if ($dinars <= $remaining && $pulses > $max) {
                $max = $pulses;
            }
        }

        return $max;
    }

    public function startProbe(string $kioskId, int $minutes = 30): void
    {
        $minutes = max(5, min(120, $minutes));
        $until = now()->addMinutes($minutes);

        Cache::put($this->probeKey($kioskId), [
            'until' => $until->getTimestamp(),
        ], $until);
    }

    public function stopProbe(string $kioskId): void
    {
        Cache::forget($this->probeKey($kioskId));
    }

    public function isProbing(string $kioskId): bool
    {
        $row = Cache::get($this->probeKey($kioskId));

        return is_array($row) && (int) ($row['until'] ?? 0) > now()->getTimestamp();
    }

    public function probeUntil(string $kioskId): ?string
    {
        $row = Cache::get($this->probeKey($kioskId));
        $until = (int) (is_array($row) ? ($row['until'] ?? 0) : 0);

        if ($until < now()->getTimestamp()) {
            return null;
        }

        return now()->setTimestamp($until)->toIso8601String();
    }

    /**
     * @return array<string, mixed>
     */
    public function window(string $kioskId): array
    {
        $probing = $this->isProbing($kioskId);

        $job = PrintJob::query()
            ->where('kiosk_id', $kioskId)
            ->where('status', 'awaiting_payment')
            ->where('payment_method', 'monnayeur')
            ->orderBy('created_at')
            ->first();

        if ($probing) {
            // Open every mapped coin so wiring can be checked without a print job.
            return [
                'open' => true,
                'probe' => true,
                'job_id' => $job?->id,
                'remaining' => $job ? max(0, $this->due($job) - $this->inserted($job)) : 200,
                'max_pulses' => 6,
            ];
        }

        if (! $job) {
            return ['open' => false, 'max_pulses' => 0, 'remaining' => 0];
        }

        $remaining = max(0, $this->due($job) - $this->inserted($job));

        return [
            'open' => true,
            'job_id' => $job->id,
            'remaining' => $remaining,
            // Accept any mapped coin while paying; overshoot is credited and printed.
            'max_pulses' => 6,
        ];
    }

    public function credit(string $kioskId, int $pulses, string $eventKey, bool $returned = false): array
    {
        $dinars = self::DINARS[$pulses] ?? null;
        $acceptor = $returned ? 'back' : 'coin';

        if ($dinars === null) {
            $payload = $this->emptyView('unknown');
            $this->broadcastSeen($kioskId, $pulses, null, $acceptor, 'unknown', null, $eventKey);

            return $payload;
        }

        $payload = $this->apply($kioskId, null, $pulses, $dinars, $eventKey, $returned);
        $result = (string) ($payload['result'] ?? 'ignored');

        if ($result === 'ignored' && $this->isProbing($kioskId)) {
            $result = 'probe';
            $payload['result'] = 'probe';
        }

        $this->broadcastSeen(
            $kioskId,
            $pulses,
            $dinars,
            $acceptor,
            $result,
            $payload['job_id'] ?? null,
            $eventKey,
        );

        return $payload;
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
    private function apply(string $kioskId, ?string $onlyJobId, int $pulses, int $dinars, string $eventKey, bool $returned = false): array
    {
        try {
            $outcome = DB::transaction(function () use ($kioskId, $onlyJobId, $pulses, $dinars, $eventKey, $returned) {
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
                $already = $this->inserted($job);
                $inserted = $already + $dinars;

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
            ExpireUnpaidPrint::dispatch($job->id)->delay(
                now()->addSeconds(max(5, (int) config('print.coin_hold_seconds', 60)))
            );
        }

        if ($outcome['kind'] === 'paid' && $job) {
            app(OrderNotifier::class)->complete($job->fresh());
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

    private function broadcastSeen(
        string $kioskId,
        int $pulses,
        ?int $dinars,
        string $acceptor,
        string $result,
        ?string $jobId,
        string $eventKey,
    ): void {
        $this->broadcastSafely(new KioskCoinSeen([
            'kiosk_id' => $kioskId,
            'pulses' => $pulses,
            'dinars' => $dinars,
            'acceptor' => $acceptor,
            'result' => $result,
            'job_id' => $jobId,
            'event_key' => $eventKey,
            'at' => now()->toIso8601String(),
        ]));
    }

    private function probeKey(string $kioskId): string
    {
        return 'kiosk-coin-probe:'.$kioskId;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyView(string $result): array
    {
        return $this->view(null, $result);
    }

    public function abandon(PrintJob $job): bool
    {
        $failed = false;

        DB::transaction(function () use ($job, &$failed) {
            $locked = PrintJob::query()->whereKey($job->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'awaiting_payment' || $locked->payment_method !== 'monnayeur') {
                return;
            }

            $locked->update(['status' => 'failed']);
            $failed = true;
        });

        if ($failed) {
            $fresh = $job->fresh();
            if ($fresh) {
                $this->broadcastSafely(new PrintJobFailed($fresh, 'abandoned'));
            }
        }

        return $failed;
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
