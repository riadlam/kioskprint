<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrintFilesRequest;
use App\Models\PrintJob;
use App\Services\Print\PrintDocumentStore;
use App\Services\Print\PrintJobSession;
use App\Services\Print\PrintTemplateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class PrintJobController extends Controller
{
    public function __construct(
        private PrintDocumentStore $documents,
        private PrintJobSession $session,
        private PrintTemplateCatalog $templates,
    ) {}

    public function create(Request $request): RedirectResponse|Response
    {
        $this->session->captureKioskFromRequest($request);

        if ($pin = $this->pinScreen()) {
            return $pin;
        }

        $ids = session('print_job_ids', []);

        if ($ids !== []) {
            $existing = PrintJob::query()
                ->whereIn('id', $ids)
                ->where('status', 'reviewing')
                ->latest()
                ->first();

            if ($existing) {
                if ($this->session->kioskId() && ! $existing->kiosk_id) {
                    $existing->update(['kiosk_id' => $this->session->kioskId()]);
                }

                return redirect()->route('print.review', $existing);
            }
        }

        $job = PrintJob::query()->create([
            'locale' => 'ar',
            'status' => 'reviewing',
            'kiosk_id' => $this->session->kioskId(),
        ]);

        $this->session->remember($job);
        $this->session->consumeDesk();

        return redirect()->route('print.review', $job);
    }

    public function store(StorePrintFilesRequest $request): RedirectResponse|Response
    {
        $this->session->captureKioskFromRequest($request);

        if ($pin = $this->pinScreen()) {
            return $pin;
        }

        $job = PrintJob::query()->create([
            'locale' => $request->locale(),
            'status' => 'reviewing',
            'kiosk_id' => $this->session->kioskId(),
        ]);

        try {
            $this->documents->add($job, $request->file('files', []), $request->locale());
        } catch (Throwable $exception) {
            $job->documents()->each(fn ($document) => $document->delete());
            $job->delete();

            throw $exception;
        }

        $this->session->remember($job);
        $this->session->consumeDesk();

        return redirect()->route('print.review', $job);
    }

    public function show(PrintJob $job): Response|RedirectResponse
    {
        if (! $this->session->owns($job)) {
            return redirect()->route('print.create');
        }

        $job->load(['documents']);

        $documents = $job->documents->keyBy('id');
        $orderedPages = $job->pages()->get();

        return Inertia::render('Print/Review', [
            'job' => [
                'id' => $job->id,
                'locale' => $job->locale,
                'kiosk_id' => $job->kiosk_id,
                'duplex' => (bool) $job->duplex,
                'total_pages' => $orderedPages->count(),
                'documents' => $job->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'name' => $document->original_name,
                    'mime' => $document->mime_type,
                    'page_count' => $document->page_count,
                    'url' => route('print.documents.file', [$job, $document]),
                ])->values(),
                'pages' => $orderedPages->values()->map(function ($page, $index) use ($documents, $job) {
                    $document = $documents->get($page->print_document_id);

                    return [
                        'id' => $page->id,
                        'number' => $page->number,
                        'position' => $page->position,
                        'index' => $index + 1,
                        'document' => [
                            'id' => $document->id,
                            'name' => $document->original_name,
                            'mime' => $document->mime_type,
                            'page_count' => $document->page_count,
                            'url' => route('print.documents.file', [$job, $document]),
                        ],
                    ];
                }),
            ],
            'limits' => [
                'max_files' => (int) config('print.max_files'),
                'max_kilobytes' => (int) config('print.max_kilobytes'),
            ],
            'templates' => $this->templates->forClient(),
            'templatePrice' => (float) config('print.template_price_per_page'),
        ]);
    }

    private function pinScreen(): ?Response
    {
        if ($this->session->kioskId() === null || $this->session->hasDeskGrant() || $this->ownsReviewingJob()) {
            return null;
        }

        return Inertia::render('Print/Pin', [
            'kioskId' => $this->session->kioskId(),
        ]);
    }

    private function ownsReviewingJob(): bool
    {
        $ids = session('print_job_ids', []);

        if ($ids === []) {
            return false;
        }

        return PrintJob::query()
            ->whereIn('id', $ids)
            ->where('status', 'reviewing')
            ->exists();
    }
}
