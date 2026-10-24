<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintPage extends Model
{
    protected $fillable = [
        'print_document_id',
        'number',
        'position',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(PrintDocument::class, 'print_document_id');
    }
}
