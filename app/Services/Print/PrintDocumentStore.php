<?php

namespace App\Services\Print;

use App\Models\PrintJob;
use App\Models\PrintPage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class PrintDocumentStore
{
    public function __construct(private PageCounter $pages) {}

    public function addFromPath(PrintJob $job, string $absolutePath, string $originalName, string $locale): void
    {
        $file = new UploadedFile(
            $absolutePath,
            $originalName,
            'application/pdf',
            \UPLOAD_ERR_OK,
            true,
        );

        $this->add($job, [$file], $locale);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function add(PrintJob $job, array $files, string $locale): void
    {
        $max = (int) config('print.max_files');
        $existing = $job->documents()->count();

        if ($existing + count($files) > $max) {
            throw ValidationException::withMessages([
                'files' => $locale === 'ar'
                    ? "يمكن إضافة {$max} ملفات كحد أقصى."
                    : "Vous pouvez ajouter {$max} fichiers au maximum.",
            ]);
        }

        $counted = [];
        foreach ($files as $index => $file) {
            try {
                $counted[] = ['file' => $file, 'pages' => $this->pages->count($file)];
            } catch (UnreadablePrintFile) {
                throw ValidationException::withMessages([
                    "files.{$index}" => $locale === 'ar'
                        ? 'تعذر قراءة «'.$file->getClientOriginalName().'». أزل كلمة السر أو اختر ملفاً آخر.'
                        : 'Impossible de lire «'.$file->getClientOriginalName().'». Retirez le mot de passe ou choisissez un autre fichier.',
                ]);
            }
        }

        $stored = [];

        try {
            DB::transaction(function () use ($job, $counted, &$stored) {
                $documentPosition = (int) $job->documents()->max('position');
                $pagePosition = (int) PrintPage::query()
                    ->whereIn('print_document_id', $job->documents()->select('id'))
                    ->max('position');

                foreach ($counted as $item) {
                    /** @var UploadedFile $file */
                    $file = $item['file'];
                    $documentPosition++;
                    $path = $file->store('print/'.$job->id, 'local');
                    $stored[] = $path;

                    $document = $job->documents()->create([
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'path' => $path,
                        'page_count' => $item['pages'],
                        'position' => $documentPosition,
                    ]);

                    $rows = [];
                    $now = now();
                    for ($number = 1; $number <= $item['pages']; $number++) {
                        $pagePosition++;
                        $rows[] = [
                            'print_document_id' => $document->id,
                            'number' => $number,
                            'position' => $pagePosition,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                    PrintPage::query()->insert($rows);
                }
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($stored);
            throw $exception;
        }
    }

    /**
     * @param  array<int, int|string>  $pageIds
     */
    public function reorder(PrintJob $job, array $pageIds): void
    {
        $ownedIds = $job->pages()->pluck('print_pages.id')->map(fn ($id) => (int) $id)->all();
        $incoming = array_map('intval', $pageIds);

        sort($ownedIds);
        $sortedIncoming = $incoming;
        sort($sortedIncoming);

        if ($ownedIds !== $sortedIncoming) {
            throw ValidationException::withMessages([
                'page_ids' => 'Invalid page order.',
            ]);
        }

        DB::transaction(function () use ($incoming) {
            foreach ($incoming as $index => $pageId) {
                PrintPage::query()->whereKey($pageId)->update([
                    'position' => $index + 1,
                ]);
            }
        });
    }

    public function removePage(PrintPage $page): void
    {
        $document = $page->document;
        $job = $document->job;
        $page->delete();

        if ($document->pages()->count() === 0) {
            $document->delete();
        }

        if ($job) {
            $this->compactPositions($job);
        }
    }

    public function compactPositions(PrintJob $job): void
    {
        $ids = $job->pages()->pluck('print_pages.id');
        $position = 0;

        foreach ($ids as $id) {
            $position++;
            PrintPage::query()->whereKey($id)->update(['position' => $position]);
        }
    }

    public function removeDocument(PrintDocument $document): void
    {
        $document->delete();
    }
}
