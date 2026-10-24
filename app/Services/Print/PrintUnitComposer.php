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
        $webp = str_contains(strtolower($page['mime']), 'webp')
            || str_ends_with(strtolower($path), '.webp');

        if (! $webp) {
            return $path;
        }

        if (! function_exists('imagecreatefromwebp')) {
            throw new RuntimeException('WebP images are not supported by this PHP build.');
        }

        $image = @imagecreatefromwebp($path);

        if ($image === false) {
            throw new RuntimeException('Unreadable image.');
        }

        $jpeg = $spoolDir.DIRECTORY_SEPARATOR.'page-'.$page['id'].'.jpg';
        imagejpeg($image, $jpeg, 90);
        imagedestroy($image);

        return $jpeg;
    }
}
