<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintSetting extends Model
{
    protected $fillable = [
        'toner_save',
    ];

    protected function casts(): array
    {
        return [
            'toner_save' => 'boolean',
        ];
    }

    public static function current(): self
    {
        $existing = static::query()->orderBy('id')->first();

        if ($existing) {
            return $existing;
        }

        return static::query()->create([
            'toner_save' => false,
        ]);
    }
}
