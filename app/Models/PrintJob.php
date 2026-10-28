<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class PrintJob extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'locale',
        'kiosk_id',
        'print_manifest',
        'duplex',
        'status',
        'page_count',
        'unit_price',
        'printed_pages',
        'amount',
        'amount_inserted',
        'currency',
        'payment_method',
        'paid_at',
        'telegram_message_id',
    ];

    protected function casts(): array
    {
        return [
            'duplex' => 'boolean',
            'print_manifest' => 'array',
            'printed_pages' => 'integer',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'amount_inserted' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PrintDocument::class)->orderBy('position');
    }

    public function pages(): HasManyThrough
    {
        return $this->hasManyThrough(PrintPage::class, PrintDocument::class)
            ->orderBy('print_pages.position');
    }
}
