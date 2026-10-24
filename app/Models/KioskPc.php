<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KioskPc extends Model
{
    protected $fillable = [
        'kiosk_id',
        'last_error',
        'last_error_at',
    ];

    protected function casts(): array
    {
        return [
            'last_error_at' => 'datetime',
        ];
    }
}
