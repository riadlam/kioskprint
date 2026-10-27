<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrintTemplate extends Model
{
    protected $fillable = [
        'slug',
        'title_ar',
        'title_fr',
        'path',
        'pages',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'pages' => 'integer',
            'position' => 'integer',
        ];
    }
}
