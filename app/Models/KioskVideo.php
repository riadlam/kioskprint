<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskVideo extends Model
{
    protected $fillable = [
        'position',
        'original_name',
        'path',
        'bytes',
        'sha256',
        'is_active',
        'volume_percent',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'bytes' => 'integer',
            'is_active' => 'boolean',
            'volume_percent' => 'integer',
        ];
    }
}
