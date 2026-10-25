<?php

namespace Tests\Feature;

use App\Services\Print\PrintUnitComposer;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrintSheetSizeTest extends TestCase
{
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
