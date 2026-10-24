<?php

namespace App\Services\Print;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;
use Throwable;

class PageCounter
{
    public function count(UploadedFile $file): int
    {
        $mime = strtolower((string) $file->getMimeType());

        if (str_starts_with($mime, 'image/')) {
            $info = @getimagesize($file->getRealPath());
            if ($info === false) {
                throw new UnreadablePrintFile('image');
            }

            return 1;
        }

        try {
            $pdf = (new Parser())->parseFile($file->getRealPath());
            $count = count($pdf->getPages());
        } catch (Throwable) {
            throw new UnreadablePrintFile('pdf');
        }

        if ($count < 1) {
            throw new UnreadablePrintFile('pdf');
        }

        return $count;
    }
}
