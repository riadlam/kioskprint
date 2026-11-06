<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaperUse extends Model
{
    protected $fillable = [
        'print_job_id',
        'sheets',
    ];

    protected function casts(): array
    {
        return [
            'sheets' => 'integer',
        ];
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class, 'print_job_id');
    }
}
