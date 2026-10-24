<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskScreenSetting extends Model
{
    protected $fillable = [
        'enabled',
        'idle_seconds',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'idle_seconds' => 'integer',
        ];
    }

    public static function current(): self
    {
        $existing = static::query()->orderBy('id')->first();

        if ($existing) {
            return $existing;
        }

        return static::query()->create([
            'enabled' => true,
            'idle_seconds' => 60,
        ]);
    }
}
