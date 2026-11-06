<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaperStock extends Model
{
    protected $fillable = [
        'sheets_left',
        'filled_sheets',
        'filled_at',
        'low_alerted_at',
    ];

    protected function casts(): array
    {
        return [
            'sheets_left' => 'integer',
            'filled_sheets' => 'integer',
            'filled_at' => 'datetime',
            'low_alerted_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        $existing = static::query()->orderBy('id')->first();

        if ($existing) {
            return $existing;
        }

        return static::query()->create([
            'sheets_left' => 0,
            'filled_sheets' => 0,
        ]);
    }
}
