<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePrintFilesRequest;
use App\Models\PrintDocument;
use App\Models\PrintJob;
use App\Services\Print\PrintDocumentStore;
use App\Services\Print\PrintJobSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrintDocumentController extends Controller
{
    public function __construct(
        private PrintDocumentStore $documents,
        private PrintJobSession $session,
    ) {}

    public function store(StorePrintFilesRequest $request, PrintJob $job): RedirectResponse
    {
        $this->guard($job);
        $job->update(['locale' => $request->locale()]);
        $this->documents->add($job, $request->file('files', []), $request->locale());

        return redirect()->route('print.review', $job);
    }

    public function destroy(PrintJob $job, PrintDocument $document): RedirectResponse
    {
        $this->guard($job);
        abort_unless($document->print_job_id === $job->id, 404);
        $this->documents->removeDocument($document);

        return redirect()->route('print.review', $job);
    }

    public function file(PrintJob $job, PrintDocument $document): StreamedResponse
    {
        $this->guard($job);
        abort_unless($document->print_job_id === $job->id, 404);

        return Storage::disk('local')->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    private function guard(PrintJob $job): void
    {
        abort_unless($this->session->owns($job), 403);
    }
}
