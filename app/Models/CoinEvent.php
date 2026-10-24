<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CoinEvent extends Model
{
    protected $fillable = [
        'print_job_id',
        'kiosk_id',
        'pulses',
        'dinars',
        'event_key',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class, 'print_job_id');
    }
}
