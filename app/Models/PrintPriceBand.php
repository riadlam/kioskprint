<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintPriceBand extends Model
{
    protected $fillable = [
        'min_pages',
        'max_pages',
        'price_per_page',
    ];

    protected function casts(): array
    {
        return [
            'min_pages' => 'integer',
            'max_pages' => 'integer',
            'price_per_page' => 'decimal:2',
        ];
    }
}
