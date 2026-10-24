<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KioskPrintUnit extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'print_job_id',
        'kiosk_id',
        'position',
        'page_ids',
        'path',
        'duplex',
        'state',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'page_ids' => 'array',
            'duplex' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class, 'print_job_id');
    }
}
