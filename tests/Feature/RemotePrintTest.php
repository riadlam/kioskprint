<?php

namespace Tests\Feature;

use App\Services\Print\RemoteKioskPrinter;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RemotePrintTest extends TestCase
{
    public function test_remote_printer_posts_the_pdf_to_the_kiosk(): void
    {
        Http::fake([
            'http://kiosk.test/print' => Http::response('printed', 200),
        ]);

        $path = storage_path('framework/testing/remote-page.pdf');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, "%PDF-1.4\n");

        $printer = new RemoteKioskPrinter('http://kiosk.test/print', 'secret-token', 30);
        $called = false;
        $printer->send($path, true, [1], function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
        Http::assertSent(function ($request) {
            return $request->url() === 'http://kiosk.test/print'
                && $request->hasHeader('X-Kiosk-Token', 'secret-token')
                && $request->hasHeader('X-Print-Settings', 'fit,duplexlong')
                && str_starts_with($request->body(), '%PDF');
        });
    }

    public function test_remote_printer_refuses_a_kiosk_error(): void
    {
        Http::fake([
            'http://kiosk.test/print' => Http::response('offline', 500),
        ]);

        $path = storage_path('framework/testing/remote-page.pdf');
        file_put_contents($path, "%PDF-1.4\n");

        $printer = new RemoteKioskPrinter('http://kiosk.test/print', 'secret-token', 30);

        $this->expectException(RuntimeException::class);
        $printer->send($path, false, [1]);
    }
}
