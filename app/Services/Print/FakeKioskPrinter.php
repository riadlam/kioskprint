<?php

namespace App\Services\Print;

use RuntimeException;

class FakeKioskPrinter implements KioskPrinter
{
    /** @var list<array{path: string, duplex: bool, page_ids: list<int>}> */
    public array $units = [];

    public bool $fail = false;

    public function send(string $pdfPath, bool $duplex, array $pageIds, ?callable $onSpooled = null): void
    {
        if ($this->fail) {
            throw new RuntimeException('Printer unavailable.');
        }

        if (! is_file($pdfPath) || filesize($pdfPath) < 1) {
            throw new RuntimeException('Print file is missing.');
        }

        $this->units[] = [
            'path' => $pdfPath,
            'duplex' => $duplex,
            'page_ids' => array_map('intval', $pageIds),
        ];

        if ($onSpooled) {
            $onSpooled();
        }
    }
}
