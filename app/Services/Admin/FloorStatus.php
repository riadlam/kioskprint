<?php

namespace App\Services\Admin;

use App\Models\CoinEvent;
use App\Models\KioskPc;
use App\Models\KioskPrintUnit;
use App\Models\PrintJob;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pusher\Pusher;
use Throwable;

class FloorStatus
{
    /**
     * @return array{checked_at: string, signals: list<array<string, string>>, machines: list<array<string, mixed>>}
     */
    public function report(): array
    {
        $presence = $this->presence();
        $machines = $this->machines($presence);
        $live = collect($machines)->firstWhere('live', true);

        return [
            'checked_at' => now()->timezone(config('app.timezone'))->format('H:i:s'),
            'signals' => [
                $this->database(),
                $this->storage(),
                $this->reverb(),
                $this->socket($live),
                $this->coins($live),
                $this->printer($live),
                $this->queue(),
            ],
            'machines' => $machines,
        ];
    }

    /**
     * @return array{ok: bool, ids: list<string>}
     */
    private function presence(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return ['ok' => false, 'ids' => []];
        }

        try {
            $config = config('broadcasting.connections.reverb');
            $options = $config['options'] ?? [];
            $pusher = new Pusher(
                (string) ($config['key'] ?? ''),
                (string) ($config['secret'] ?? ''),
                (string) ($config['app_id'] ?? ''),
                [
                    'host' => $options['host'] ?? '127.0.0.1',
                    'port' => (int) ($options['port'] ?? 80),
                    'scheme' => $options['scheme'] ?? 'http',
                    'useTLS' => (bool) ($options['useTLS'] ?? false),
                    'timeout' => 2,
                ],
            );
            $result = $pusher->getPresenceUsers('presence-kiosk-pcs');
            $ids = [];

            foreach ($result->users ?? [] as $user) {
                $id = is_object($user) ? ($user->id ?? null) : ($user['id'] ?? null);
                if (is_string($id) && $id !== '' && $id !== 'desk') {
                    $ids[] = $id;
                }
            }

            return ['ok' => true, 'ids' => array_values(array_unique($ids))];
        } catch (Throwable) {
            return ['ok' => false, 'ids' => []];
        }
    }

    /**
     * @param  array{ok: bool, ids: list<string>}  $presence
     * @return list<array<string, mixed>>
     */
    private function machines(array $presence): array
    {
        $pcs = Schema::hasTable('kiosk_pcs')
            ? KioskPc::query()->get()->keyBy('kiosk_id')
            : collect();
        $ids = $pcs->keys()->merge(
            PrintJob::query()->whereNotNull('kiosk_id')->distinct()->pluck('kiosk_id'),
        )->merge($presence['ids'])->unique()->filter()->values();

        $latestJob = PrintJob::query()
            ->whereIn('kiosk_id', $ids)
            ->selectRaw('kiosk_id, max(updated_at) as seen')
            ->groupBy('kiosk_id')
            ->pluck('seen', 'kiosk_id');

        $liveIds = $presence['ok'] && $presence['ids'] !== []
            ? $presence['ids']
            : [$this->fallbackLiveId($ids, $pcs, $latestJob)];

        return $ids->map(function ($id) use ($pcs, $presence, $liveIds, $latestJob) {
            $pc = $pcs->get($id);
            $live = in_array((string) $id, $liveIds, true);
            $online = $presence['ok'] && in_array((string) $id, $presence['ids'], true);

            if ($online) {
                $pc = $pc ?? new KioskPc(['kiosk_id' => (string) $id]);
                if ($pc->connected_since === null) {
                    $pc->connected_since = now();
                }
                if ($pc->last_seen_at === null) {
                    $pc->last_seen_at = now();
                }
                $pc->save();
            }

            $lastSeen = $pc?->last_seen_at;

            if ($live && $online) {
                $state = 'up';
                $reason = 'Its socket is on the live channel right now.';
                $meter = $pc?->connected_since
                    ? 'Up for '.$this->span($pc->connected_since)
                    : 'Up right now';
            } elseif ($live && ! $presence['ok']) {
                $state = 'quiet';
                $reason = 'The desk could not read the live channel, so this is the kiosk that checked in most recently.';
                $meter = $lastSeen ? 'Last heard '.$lastSeen->diffForHumans() : 'No check-in stored yet';
            } elseif ($live) {
                $state = 'down';
                $reason = $pc?->last_error
                    ? 'Off the live channel. Last printer note: '.$pc->last_error
                    : 'Off the live channel. A finished print does not keep this light on.';
                $meter = $lastSeen ? 'Last heard '.$lastSeen->diffForHumans() : 'Never checked in';
            } else {
                $state = 'quiet';
                $reason = 'Earlier install. The tablet used this id before the current one, so it is history, not a fault.';
                $meter = isset($latestJob[$id])
                    ? 'Last job '.$this->ago(Carbon::parse($latestJob[$id]))
                    : 'No recent job';
            }

            return [
                'kiosk_id' => (string) $id,
                'live' => $live,
                'state' => $state,
                'reason' => $reason,
                'meter' => $meter,
                'last_error' => $pc?->last_error,
            ];
        })->sortBy([
            ['live', 'desc'],
            ['kiosk_id', 'asc'],
        ])->values()->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, mixed>  $ids
     * @param  \Illuminate\Support\Collection<string, KioskPc>  $pcs
     * @param  \Illuminate\Support\Collection<string, mixed>  $latestJob
     */
    private function fallbackLiveId($ids, $pcs, $latestJob): ?string
    {
        $bySeen = $pcs->sortByDesc(fn (KioskPc $pc) => $pc->last_seen_at?->getTimestamp() ?? 0)->first();
        if ($bySeen && $bySeen->last_seen_at) {
            return $bySeen->kiosk_id;
        }

        $byTouch = $pcs->sortByDesc(fn (KioskPc $pc) => $pc->updated_at?->getTimestamp() ?? 0)->first();
        if ($byTouch) {
            return $byTouch->kiosk_id;
        }

        $jobId = $latestJob->sortDesc()->keys()->first();

        return $jobId ? (string) $jobId : ($ids->first() ? (string) $ids->first() : null);
    }

    /**
     * @param  array<string, mixed>|null  $live
     * @return array<string, string>
     */
    private function socket(?array $live): array
    {
        if (! $live) {
            return $this->signal('socket', 'Mini PC', 'quiet', 'Nobody has connected', 'No kiosk PC has introduced itself yet.', 'Waiting for the first check-in');
        }

        return $this->signal(
            'socket',
            'Mini PC',
            (string) $live['state'],
            match ($live['state']) {
                'up' => 'On the line',
                'down' => 'Left the line',
                default => 'Check-in only',
            },
            (string) $live['reason'],
            (string) $live['meter'],
        );
    }

    /**
     * @param  array<string, mixed>|null  $live
     * @return array<string, string>
     */
    private function coins(?array $live): array
    {
        if (! $live || ! Schema::hasTable('coin_events')) {
            return $this->signal('coin', 'Coin reader', 'quiet', 'Resting', 'The reader only speaks when a coin drops.', 'No coin yet');
        }

        $event = CoinEvent::query()->where('kiosk_id', $live['kiosk_id'])->latest()->first();

        if (! $event) {
            return $this->signal('coin', 'Coin reader', 'quiet', 'Resting', 'No coin has been credited on the live kiosk. Silence here is normal until someone pays.', 'No coin yet');
        }

        $amount = (int) $event->dinars;
        $when = $event->created_at?->diffForHumans() ?? 'just now';

        if ($event->print_job_id === null) {
            return $this->signal('coin', 'Coin reader', 'down', 'Last coin was missed', "A {$amount} DA coin arrived {$when}, but no session was waiting for it.", "Missed {$when}");
        }

        $fresh = $event->created_at && $event->created_at->gt(now()->subMinutes(15));

        return $this->signal(
            'coin',
            'Coin reader',
            $fresh ? 'up' : 'quiet',
            $fresh ? 'Money is landing' : 'Resting',
            "Last coin was {$amount} DA, {$when}. The reader stays quiet until the next coin.",
            $fresh ? 'Heard '.$this->span($event->created_at) : "Last coin {$when}",
        );
    }

    /**
     * @param  array<string, mixed>|null  $live
     * @return array<string, string>
     */
    private function printer(?array $live): array
    {
        if (! $live || ! Schema::hasTable('kiosk_print_units')) {
            return $this->signal('printer', 'Canon printer', 'quiet', 'No sheet yet', 'A successful older job stays here as resting, not as a failure.', 'Nothing sent');
        }

        $unit = KioskPrintUnit::query()->where('kiosk_id', $live['kiosk_id'])->latest()->first();

        if (! $unit) {
            return $this->signal('printer', 'Canon printer', 'quiet', 'No sheet yet', 'This kiosk has not sent a sheet to the Canon.', 'Nothing sent');
        }

        $when = $unit->updated_at?->diffForHumans() ?? 'just now';

        if ($unit->state === 'failed') {
            $why = $unit->error && $unit->error !== 'stopped' ? $unit->error : 'The printer rejected the sheet.';

            return $this->signal('printer', 'Canon printer', 'down', 'Sheet refused', $why, "Failed {$when}");
        }

        if ($unit->state === 'pending') {
            return $this->signal('printer', 'Canon printer', 'up', 'Sheet in hand', 'A paid sheet is on its way to the Canon.', "Waiting {$when}");
        }

        $fresh = $unit->updated_at && $unit->updated_at->gt(now()->subMinutes(15));

        return $this->signal(
            'printer',
            'Canon printer',
            $fresh ? 'up' : 'quiet',
            $fresh ? 'Sheet came out' : 'Resting after a good print',
            "The last sheet printed {$when}. A finished job stays green in the job list even while this light rests.",
            $fresh ? 'Printed '.$this->span($unit->updated_at) : "Last print {$when}",
        );
    }

    /**
     * @return array<string, string>
     */
    private function database(): array
    {
        try {
            DB::select('select 1 as ok');

            return $this->signal('database', 'Database', 'up', 'Answering', 'MySQL answered a check just now.', 'Up right now');
        } catch (Throwable) {
            return $this->signal('database', 'Database', 'down', 'Not answering', 'MySQL did not answer. Jobs and coins cannot be saved until it does.', 'Down right now');
        }
    }

    /**
     * @return array<string, string>
     */
    private function storage(): array
    {
        $writable = is_writable(storage_path('app'));

        return $writable
            ? $this->signal('storage', 'Print files', 'up', 'Ready for sheets', 'The server can store the PDFs the Canon prints.', 'Up right now')
            : $this->signal('storage', 'Print files', 'down', 'Cannot save sheets', 'The storage folder is not writable, so a paid job cannot become a PDF.', 'Down right now');
    }

    /**
     * @return array<string, string>
     */
    private function reverb(): array
    {
        $port = (int) config('reverb.servers.reverb.port', 8080);
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.4);
        $live = is_resource($socket);
        if ($live) {
            fclose($socket);
        }

        return $live
            ? $this->signal('reverb', 'Live wire', 'up', 'Open', "Reverb is listening on port {$port}. Coins, payment, and print jobs travel on this wire.", 'Up right now')
            : $this->signal('reverb', 'Live wire', 'down', 'Closed', "Nothing is listening on 127.0.0.1:{$port}. The website cannot hear the mini PC until Reverb is back.", 'Down right now');
    }

    /**
     * @return array<string, string>
     */
    private function queue(): array
    {
        $failed = Schema::hasTable('failed_jobs') ? (int) DB::table('failed_jobs')->count() : 0;

        if ($failed === 0) {
            return $this->signal('queue', 'Background jobs', 'up', 'Clear', 'No failed background jobs. Paid printing does not wait on this queue.', 'Nothing stuck');
        }

        return $this->signal(
            'queue',
            'Background jobs',
            'quiet',
            'Old failures, not the printer',
            $failed.' failed background job'.($failed === 1 ? '' : 's').' are still on file. A sheet that already printed does not clear them, and the Canon does not wait on them.',
            $failed.' on file',
        );
    }

    /**
     * @return array<string, string>
     */
    private function signal(string $key, string $name, string $state, string $headline, string $reason, string $meter): array
    {
        return compact('key', 'name', 'state', 'headline', 'reason', 'meter');
    }

    private function span(?CarbonInterface $from): string
    {
        if (! $from) {
            return 'just now';
        }

        return $from->diffForHumans(now(), [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            'parts' => 2,
            'short' => false,
        ]);
    }

    private function ago(CarbonInterface $time): string
    {
        return $time->diffForHumans();
    }
}
