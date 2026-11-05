<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class PrintCode extends Model
{
    use HasUuids;
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'title',
        'pin',
        'original_name',
        'path',
        'mime_type',
        'page_count',
        'unit_price',
        'currency',
        'is_active',
        'print_count',
    ];

    protected function casts(): array
    {
        return [
            'page_count' => 'integer',
            'unit_price' => 'decimal:2',
            'is_active' => 'boolean',
            'print_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobs(): HasMany
    {
        return $this->hasMany(PrintJob::class);
    }

    public function amountDue(): float
    {
        return round((float) $this->page_count * (float) $this->unit_price, 2);
    }

    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->path);
    }

    public function releasePin(): void
    {
        $this->forceFill([
            'pin' => null,
            'is_active' => false,
        ])->save();
    }
}
