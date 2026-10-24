<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class PrintDocument extends Model
{
    protected $fillable = [
        'print_job_id',
        'original_name',
        'mime_type',
        'path',
        'page_count',
        'position',
    ];

    protected static function booted(): void
    {
        static::deleting(function (PrintDocument $document) {
            Storage::disk('local')->delete($document->path);
        });
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(PrintJob::class, 'print_job_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(PrintPage::class)->orderBy('position')->orderBy('number');
    }
}
