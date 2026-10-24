<?php

namespace App\Services\Print;

use RuntimeException;

class UnreadablePrintFile extends RuntimeException
{
    public function __construct(public readonly string $kind)
    {
        parent::__construct($kind);
    }
}
