<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\PrintPage;
use App\Services\Print\PrintDocumentStore;
use App\Services\Print\PrintJobSession;
use Illuminate\Http\RedirectResponse;

class PrintPageController extends Controller
{
    public function __construct(
        private PrintDocumentStore $documents,
        private PrintJobSession $session,
    ) {}

    public function destroy(PrintJob $job, PrintPage $page): RedirectResponse
    {
        abort_unless($this->session->owns($job), 403);
        abort_unless($page->document?->print_job_id === $job->id, 404);

        $this->documents->removePage($page);

        return redirect()->route('print.review', $job);
    }
}
