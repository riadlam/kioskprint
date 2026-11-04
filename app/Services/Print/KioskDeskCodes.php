<?php

namespace App\Services\Print;

use Illuminate\Support\Facades\Cache;

class KioskDeskCodes
{
    /** How long a published desk code stays valid on the server. */
    public const TTL_MINUTES = 10;

    public function publish(string $kioskId, string $code): void
    {
        // Keep the code longer than the tablet's refresh interval so a delayed
        // Android timer / brief offline blip does not leave a dead code on screen.
        Cache::put($this->key($kioskId), [
            'code' => $code,
            'expires_at' => now()->addMinutes(self::TTL_MINUTES)->getTimestamp(),
        ], now()->addMinutes(self::TTL_MINUTES));
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
