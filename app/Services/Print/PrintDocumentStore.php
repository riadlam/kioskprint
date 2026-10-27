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
     * Store files and only the pages the customer kept, in their order.
     * Called when they pay, so review stays on the phone.
     *
     * @param  array<int, UploadedFile>  $files
     * @param  list<array{0: int, 1: int, 2?: int}>  $selection
     */
    public function hold(PrintJob $job, array $files, array $selection, string $locale): void
    {
        $max = (int) config('print.max_files');

        if (count($files) > $max) {
            throw ValidationException::withMessages([
                'files' => $locale === 'ar'
                    ? "يمكن إضافة {$max} ملفات كحد أقصى."
                    : "Vous pouvez ajouter {$max} fichiers au maximum.",
            ]);
        }

        $counted = [];
        foreach ($files as $index => $file) {
            try {
                $counted[$index] = ['file' => $file, 'pages' => $this->pages->count($file)];
            } catch (UnreadablePrintFile) {
                throw ValidationException::withMessages([
                    "files.{$index}" => $locale === 'ar'
                        ? 'تعذر قراءة «'.$file->getClientOriginalName().'». أزل كلمة السر أو اختر ملفاً آخر.'
                        : 'Impossible de lire «'.$file->getClientOriginalName().'». Retirez le mot de passe ou choisissez un autre fichier.',
                ]);
            }
        }

        foreach ($selection as $index => [$fileIndex, $pageNumber]) {
            if (! isset($counted[$fileIndex]) || $pageNumber < 1 || $pageNumber > $counted[$fileIndex]['pages']) {
                throw ValidationException::withMessages([
                    "selection.{$index}" => $locale === 'ar'
                        ? 'إحدى الصفحات لم تعد موجودة في الملف.'
                        : 'Une page ne fait plus partie du fichier.',
                ]);
            }
        }

        $stored = [];

        try {
            DB::transaction(function () use ($job, $counted, $selection, &$stored) {
                $documentPosition = 0;
                $documents = [];

                foreach ($counted as $index => $item) {
                    /** @var UploadedFile $file */
                    $file = $item['file'];
                    $documentPosition++;
                    $path = $file->store('print/'.$job->id, 'local');
                    $stored[] = $path;

                    $documents[$index] = $job->documents()->create([
                        'original_name' => $file->getClientOriginalName(),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'path' => $path,
                        'page_count' => $item['pages'],
                        'position' => $documentPosition,
                    ]);
                }

                $rows = [];
                $now = now();
                $maxCopies = max(1, (int) config('print.max_copies', 10));
                foreach ($selection as $position => $chosen) {
                    [$fileIndex, $pageNumber] = $chosen;
                    $copies = max(1, min($maxCopies, (int) ($chosen[2] ?? 1)));
                    $rows[] = [
                        'print_document_id' => $documents[$fileIndex]->id,
                        'number' => $pageNumber,
                        'position' => $position + 1,
                        'copies' => $copies,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                PrintPage::query()->insert($rows);
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
     * @param  array<int, int>  $copies
     */
    public function reorder(PrintJob $job, array $pageIds, array $copies = []): void
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

        $maxCopies = max(1, (int) config('print.max_copies', 10));

        DB::transaction(function () use ($incoming, $copies, $maxCopies) {
            foreach ($incoming as $index => $pageId) {
                $update = ['position' => $index + 1];

                if (array_key_exists($index, $copies)) {
                    $update['copies'] = max(1, min($maxCopies, (int) $copies[$index]));
                }

                PrintPage::query()->whereKey($pageId)->update($update);
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
