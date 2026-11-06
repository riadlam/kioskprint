<?php

namespace App\Services\Print;

use Illuminate\Support\Facades\Cache;

class KioskDeskCodes
{
    /**
     * How long a published desk code stays valid until used.
     * The tablet only rotates after a successful claim; this TTL is a safety net
     * refreshed by occasional same-code heartbeats.
     */
    public const TTL_MINUTES = 60;

    public function publish(string $kioskId, string $code): void
    {
        Cache::put($this->key($kioskId), [
            'code' => $code,
            'reported_at' => now()->getTimestamp(),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ], now()->addMinutes(self::TTL_MINUTES));
    }

    /**
     * @return array{code: string, reported_at: int, expires_at: int}|null
     */
    public function snapshot(string $kioskId): ?array
    {
        $row = Cache::get($this->key($kioskId));

        if (! is_array($row) || (int) ($row['expires_at'] ?? 0) < now()->getTimestamp()) {
            return null;
        }

        return [
            'code' => (string) ($row['code'] ?? ''),
            'reported_at' => (int) ($row['reported_at'] ?? 0),
            'expires_at' => (int) ($row['expires_at'] ?? 0),
        ];
    }

    public function take(string $kioskId, string $code): bool
    {
        $row = Cache::get($this->key($kioskId));

        if (! is_array($row) || (int) ($row['expires_at'] ?? 0) < now()->getTimestamp()) {
            return false;
        }

        $current = (string) ($row['code'] ?? '');

        if (strlen($current) !== 3 || strlen($code) !== 3 || ! hash_equals($current, $code)) {
            return false;
        }

        Cache::forget($this->key($kioskId));

        return true;
    }

    private function key(string $kioskId): string
    {
        return 'kiosk-desk:'.$kioskId;
    }
}
