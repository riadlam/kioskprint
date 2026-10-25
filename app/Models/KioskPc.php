<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskPc extends Model
{
    protected $fillable = [
        'kiosk_id',
        'last_error',
        'last_error_at',
        'last_seen_at',
        'connected_since',
    ];

    protected function casts(): array
    {
        return [
            'last_error_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'connected_since' => 'datetime',
        ];
    }

    public function markConnected(): void
    {
        $stale = $this->last_seen_at === null || $this->last_seen_at->lt(now()->subMinutes(2));

        if ($stale || $this->connected_since === null) {
            $this->connected_since = now();
        }

        $this->last_seen_at = now();
        $this->save();
    }
}
