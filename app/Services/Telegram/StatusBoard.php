<?php

namespace App\Services\Telegram;

use App\Models\KioskPc;
use App\Services\Admin\FloorStatus;
use App\Services\Print\KioskDeskCodes;
use Illuminate\Support\Carbon;

class StatusBoard
{
    public function __construct(
        private FloorStatus $floor,
        private KioskDeskCodes $deskCodes,
    ) {}

    public function text(): string
    {
        $kioskId = (string) config('print.public_kiosk_id', 'kiosk-metbati-01');
        $report = $this->floor->report();
        $signals = collect($report['signals'])->keyBy('key');
        $pc = KioskPc::query()->where('kiosk_id', $kioskId)->first();
        $health = is_array($pc?->health) ? $pc->health : [];
        $fresh = $pc?->health_reported_at && $pc->health_reported_at->gt(now()->subMinutes(3));
        $machine = collect($report['machines'])->firstWhere('kiosk_id', $kioskId)
            ?? collect($report['machines'])->firstWhere('live', true);

        $rows = [
            $this->healthRow('🖨', 'Printer', $fresh ? (string) ($health['printer'] ?? 'quiet') : 'down', $fresh ? (string) ($health['printer_detail'] ?? 'no detail') : 'The mini PC stopped reporting'),
            $this->healthRow('👀', 'Keep-alive', $fresh ? (string) ($health['keepalive'] ?? 'down') : 'down', $fresh ? 'Watching the Canon' : 'No report from the PC'),
            $this->healthRow('🪙', 'Coin reader', $fresh ? (string) ($health['coin_agent'] ?? 'down') : 'down', $fresh ? 'Agent is running' : 'No report from the PC'),
            $this->healthRow('📟', 'ESP32', $fresh ? (string) ($health['esp32'] ?? 'quiet') : 'down', $fresh ? (string) ($health['esp_detail'] ?? 'serial') : 'No report from the PC'),
            $this->socketRow($signals->get('socket'), $machine, $fresh ? (string) ($health['socket_process'] ?? 'down') : 'down'),
            $this->healthRow('🧾', 'Spooler', $fresh ? (string) ($health['spooler'] ?? 'down') : 'down', $fresh ? 'Windows print queue' : 'No report from the PC'),
            $this->pcRow($fresh, $health),
            $this->tabletRow($kioskId),
            $this->fromSignal('📡', 'Server wire', $signals->get('reverb')),
            $this->fromSignal('🗄', 'Database', $signals->get('database')),
            $this->fromSignal('📂', 'Print files', $signals->get('storage')),
            $this->fromSignal('📬', 'Background jobs', $signals->get('queue')),
        ];

        $down = collect($rows)->where('state', 'down')->count();
        $stamp = now()->timezone(config('app.timezone'))->format('H:i');
        $banner = $down > 0
            ? "🚨 <b>{$down} thing".($down === 1 ? '' : 's')." need you</b>"
            : '✨ <b>All the lights are on</b>';

        $body = collect($rows)
            ->sortBy(fn (array $row) => match ($row['state']) {
                'down' => 0,
                'quiet' => 1,
                default => 2,
            })
            ->map(fn (array $row) => $row['line'])
            ->implode("\n");

        return implode("\n", [
            '🏠 <b>Metbati watch</b>',
            "🕐 {$stamp} · this same note updates itself",
            '',
            $banner,
            '',
            $body,
        ]);
    }

    /**
     * @param  array<string, mixed>  $health
     * @return array{state: string, line: string}
     */
    private function pcRow(bool $fresh, array $health): array
    {
        if (! $fresh) {
            return $this->healthRow('💻', 'Mini PC', 'down', 'No report in the last 3 minutes');
        }

        $uptime = trim((string) ($health['pc_uptime'] ?? ''));
        $disk = $health['disk_free_gb'] ?? null;
        $diskText = is_numeric($disk) ? round((float) $disk).' GB free' : 'disk unknown';
        $low = is_numeric($disk) && (float) $disk < 5;
        $note = trim($uptime.' · '.$diskText, ' ·');

        return $this->healthRow('💻', 'Mini PC', $low ? 'quiet' : 'up', $note !== '' ? $note : 'Up');
    }

    /**
     * @return array{state: string, line: string}
     */
    private function tabletRow(string $kioskId): array
    {
        $snap = $this->deskCodes->snapshot($kioskId);

        if ($snap === null || $snap['reported_at'] < 1) {
            return $this->healthRow('📱', 'Tablet', 'down', 'No desk code on the server');
        }

        $seen = Carbon::createFromTimestamp($snap['reported_at']);
        $minutes = (int) abs($seen->diffInMinutes(now()));
        $state = $minutes <= 12 ? 'up' : 'down';
        $note = $state === 'up'
            ? 'Desk code refreshed '.$seen->diffForHumans()
            : 'Desk code went quiet '.$seen->diffForHumans();

        return $this->healthRow('📱', 'Tablet', $state, $note);
    }

    /**
     * @param  array<string, string>|null  $signal
     * @param  array<string, mixed>|null  $machine
     * @return array{state: string, line: string}
     */
    private function socketRow(?array $signal, ?array $machine, string $process): array
    {
        $channel = (string) ($machine['state'] ?? ($signal['state'] ?? 'down'));
        $channelUp = $channel === 'up';
        $processUp = $process === 'up';

        if ($channelUp && $processUp) {
            return $this->healthRow('🔌', 'PC websocket', 'up', 'On the live channel');
        }

        if (! $processUp) {
            return $this->healthRow('🔌', 'PC websocket', 'down', 'The socket program is not running');
        }

        return $this->healthRow('🔌', 'PC websocket', 'down', 'Program is up but the live channel dropped');
    }

    /**
     * @param  array<string, string>|null  $signal
     * @return array{state: string, line: string}
     */
    private function fromSignal(string $emoji, string $name, ?array $signal): array
    {
        $state = (string) ($signal['state'] ?? 'down');
        $note = (string) ($signal['headline'] ?? 'Unknown');

        return $this->healthRow($emoji, $name, $state, $note);
    }

    /**
     * @return array{state: string, line: string}
     */
    private function healthRow(string $emoji, string $name, string $state, string $note): array
    {
        $mark = match ($state) {
            'up' => '🟢',
            'down' => '🔴',
            default => '🟡',
        };
        $safe = htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return [
            'state' => $state,
            'line' => "{$emoji} <b>{$name}</b>  {$mark} {$safe}",
        ];
    }
}
