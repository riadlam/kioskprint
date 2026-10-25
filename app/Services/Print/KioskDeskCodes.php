<?php

namespace App\Services\Print;

use Illuminate\Support\Facades\Cache;

class KioskDeskCodes
{
    public function publish(string $kioskId, string $code): void
    {
        Cache::put($this->key($kioskId), [
            'code' => $code,
            'expires_at' => now()->addMinutes(3)->getTimestamp(),
        ], now()->addMinutes(3));
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
