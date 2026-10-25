<?php

namespace App\Services\Print;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use setasign\Fpdi\Fpdi;

class PrintUnitComposer
{
    /**
     * Build one PDF per physical sheet, in the given page order.
     *
     * @param  list<array{id: int, absolute_path: string, mime: string, number: int}>  $pages
     * @return list<array{path: string, page_ids: list<int>}>
     */
    public function compose(string $jobId, array $pages, bool $duplex): array
    {
        if ($pages === []) {
            throw new RuntimeException('This job has no pages to print.');
        }

        $disk = Storage::disk('local');
        $folder = 'print-spool/'.$jobId;
        $disk->makeDirectory($folder);

        $groups = array_chunk($pages, $duplex ? 2 : 1);
        $units = [];

        foreach ($groups as $index => $group) {
            $relative = $folder.'/unit-'.($index + 1).'.pdf';
            $absolute = $disk->path($relative);
            $directory = dirname($absolute);
            if (! is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            $this->writePdf($absolute, $group);

            $units[] = [
                'path' => $absolute,
                'page_ids' => array_map(fn (array $page) => (int) $page['id'], $group),
            ];
        }

        return $units;
    }

    /**
     * @param  list<array{id: int, absolute_path: string, mime: string, number: int}>  $group
     */
    private function writePdf(string $dest, array $group): void
    {
        $pdf = new Fpdi;
        $pdf->SetAutoPageBreak(false);

        foreach ($group as $page) {
            if ($this->isPdf($page)) {
                $this->addPdfPage($pdf, $page['absolute_path'], (int) $page['number']);
            } else {
                $this->addImagePage($pdf, $page, dirname($dest));
            }
        }

        $pdf->Output('F', $dest);
    }

    /**
     * @param  array{id: int, absolute_path: string, mime: string, number: int}  $page
     */
    private function isPdf(array $page): bool
    {
        return str_contains(strtolower($page['mime']), 'pdf')
            || str_ends_with(strtolower($page['absolute_path']), '.pdf');
    }

    private function addPdfPage(Fpdi $pdf, string $path, int $number): void
    {
        if ($number < 1) {
            throw new RuntimeException('Invalid PDF page number.');
        }

        $count = $pdf->setSourceFile($path);

        if ($number > $count) {
            throw new RuntimeException('A document page is outside the file.');
        }

        $template = $pdf->importPage($number);
        $size = $pdf->getTemplateSize($template);
        $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
        $pdf->useTemplate($template);
    }

    /**
     * @param  array{id: int, absolute_path: string, mime: string, number: int}  $page
     */
    private function addImagePage(Fpdi $pdf, array $page, string $spoolDir): void
    {
        $path = $this->imagePath($page, $spoolDir);
        $info = @getimagesize($path);

        if ($info === false) {
            throw new RuntimeException('Unreadable image.');
        }

        $pdf->AddPage('P', 'A4');
        $pageW = $pdf->GetPageWidth();
        $pageH = $pdf->GetPageHeight();
        $margin = 8.0;
        $maxW = $pageW - (2 * $margin);
        $maxH = $pageH - (2 * $margin);
        $ratio = $info[0] / max(1, $info[1]);
        $drawW = $maxW;
        $drawH = $drawW / $ratio;

        if ($drawH > $maxH) {
            $drawH = $maxH;
            $drawW = $drawH * $ratio;
        }

        $pdf->Image(
            $path,
            ($pageW - $drawW) / 2,
            ($pageH - $drawH) / 2,
            $drawW,
            $drawH
        );
    }

    /**
     * @param  array{id: int, absolute_path: string, mime: string, number: int}  $page
     */
    private function imagePath(array $page, string $spoolDir): string
    {
        $path = $page['absolute_path'];
        $image = $this->loadRaster($path, (string) $page['mime']);

        if ($image === false) {
            throw new RuntimeException('Unreadable image.');
        }

        $jpeg = $spoolDir.DIRECTORY_SEPARATOR.'page-'.$page['id'].'.jpg';
        imagejpeg($image, $jpeg, 80);
        imagedestroy($image);

        return $jpeg;
    }

    /**
     * Phone photos are often 16-bit PNG. FPDF only accepts an 8-bit JPEG.
     */
    private function loadRaster(string $path, string $mime): \GdImage|false
    {
        $hint = strtolower($path.' '.$mime);
        $image = false;

        if (str_contains($hint, 'webp') && function_exists('imagecreatefromwebp')) {
            $image = @imagecreatefromwebp($path);
        } elseif (str_contains($hint, 'png')) {
            $image = @imagecreatefrompng($path);
        } else {
            $image = @imagecreatefromjpeg($path);
        }

        if ($image === false) {
            $raw = @file_get_contents($path);
            $image = is_string($raw) ? @imagecreatefromstring($raw) : false;
        }

        if ($image === false) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $flat = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefill($flat, 0, 0, $white);
        imagecopy($flat, $image, 0, 0, 0, 0, $width, $height);
        imagedestroy($image);

        return $this->fitForPrint($flat);
    }

    /**
     * A phone photo is far larger than the Canon can use. A4 at 200 dpi
     * is enough, and the smaller file reaches the mini PC much faster.
     */
    private function fitForPrint(\GdImage $image): \GdImage
    {
        $maxW = 1654;
        $maxH = 2339;
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min($maxW / max(1, $width), $maxH / max(1, $height), 1);

        if ($scale > 0.98) {
            return $image;
        }

        $nextW = max(1, (int) round($width * $scale));
        $nextH = max(1, (int) round($height * $scale));
        $small = imagecreatetruecolor($nextW, $nextH);
        imagecopyresampled($small, $image, 0, 0, 0, 0, $nextW, $nextH, $width, $height);
        imagedestroy($image);

        return $small;
    }
}
