<?php

namespace App\Services\Print;

use App\Models\PrintCode;
use RuntimeException;

class PrintCodePinGenerator
{
    public function unique(): string
    {
        for ($i = 0; $i < 40; $i++) {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

            $taken = PrintCode::query()
                ->where('pin', $pin)
                ->where('is_active', true)
                ->exists();

            if (! $taken) {
                return $pin;
            }
        }

        throw new RuntimeException('Could not allocate a free print PIN.');
    }
}
