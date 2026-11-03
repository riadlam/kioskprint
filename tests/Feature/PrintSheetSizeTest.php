<?php

namespace Tests\Feature;

use App\Services\Print\PrintUnitComposer;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class PrintSheetSizeTest extends TestCase
{
    public function test_a_compressed_pdf_is_rewritten_so_fpdi_can_print_it(): void
    {
        if (trim((string) shell_exec('command -v qpdf 2>/dev/null')) === '') {
            $this->markTestSkipped('qpdf is required to build a compressed PDF fixture.');
        }

        $plain = storage_path('framework/testing/plain-page.pdf');
        $compressed = storage_path('framework/testing/compressed-page.pdf');
        if (! is_dir(dirname($plain))) {
            mkdir(dirname($plain), 0775, true);
        }

        $maker = new Fpdi;
        $maker->AddPage('P', 'A4');
        $maker->SetFont('Helvetica', '', 18);
        $maker->Cell(0, 10, 'Compressed PDF fixture');
        $maker->Output('F', $plain);

        exec(sprintf(
            'qpdf --object-streams=generate -- %s %s 2>&1',
            escapeshellarg($plain),
            escapeshellarg($compressed),
        ), $out, $code);
        $this->assertSame(0, $code, trim(implode("\n", $out)));

        try {
            $units = app(PrintUnitComposer::class)->compose('pdf-flatten-test', [[
                'id' => 9,
                'absolute_path' => $compressed,
                'mime' => 'application/pdf',
                'number' => 1,
            ]], false);

            $this->assertCount(1, $units);
            $this->assertFileExists($units[0]['path']);
            $this->assertGreaterThan(64, filesize($units[0]['path']));
        } finally {
            Storage::disk('local')->deleteDirectory('print-spool/pdf-flatten-test');
            @unlink($plain);
            @unlink($compressed);
            @unlink($compressed.'.fpdi.pdf');
        }
    }

    public function test_a_phone_photo_is_shrunk_before_it_is_sent_to_the_printer(): void
    {
        $source = storage_path('framework/testing/phone-photo.png');
        if (! is_dir(dirname($source))) {
            mkdir(dirname($source), 0775, true);
        }

        $image = imagecreatetruecolor(4000, 3000);
        imagefilledrectangle($image, 0, 0, 3999, 2999, imagecolorallocate($image, 20, 40, 80));
        imagepng($image, $source);
        imagedestroy($image);

        try {
            $units = app(PrintUnitComposer::class)->compose('shrink-test', [[
                'id' => 7,
                'absolute_path' => $source,
                'mime' => 'image/png',
                'number' => 1,
            ]], false);

            $info = getimagesize(dirname($units[0]['path']).DIRECTORY_SEPARATOR.'page-7.jpg');
            $this->assertNotFalse($info);
            $this->assertLessThanOrEqual(1700, max($info[0], $info[1]));
            $this->assertLessThan(500_000, filesize($units[0]['path']));
        } finally {
            Storage::disk('local')->deleteDirectory('print-spool/shrink-test');
            @unlink($source);
        }
    }
}
