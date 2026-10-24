<?php

namespace App\Services\Print;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class RemoteKioskPrinter implements KioskPrinter
{
    public function __construct(
        private string $url,
        private string $token,
        private int $pageTimeoutSeconds = 90,
    ) {}

    public function send(string $pdfPath, bool $duplex, array $pageIds, ?callable $onSpooled = null): void
    {
        if ($this->url === '' || $this->token === '') {
            throw new RuntimeException('The kiosk print address is not configured.');
        }

        if (! is_file($pdfPath)) {
            throw new RuntimeException('Print file is missing.');
        }

        $bytes = file_get_contents($pdfPath);
        if ($bytes === false || ! str_starts_with($bytes, '%PDF')) {
            throw new RuntimeException('Print file is missing.');
        }

        $response = Http::withHeaders([
            'X-Kiosk-Token' => $this->token,
            'X-Print-Settings' => $duplex ? 'fit,duplexlong' : 'fit',
        ])
            ->withBody($bytes, 'application/pdf')
            ->timeout(max(30, $this->pageTimeoutSeconds))
            ->post($this->url);

        if (! $response->successful()) {
            $detail = trim($response->body());
            throw new RuntimeException($detail !== '' ? $detail : 'The kiosk printer rejected the document.');
        }

        if ($onSpooled) {
            $onSpooled();
        }
    }
}
