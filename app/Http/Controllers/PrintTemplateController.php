<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Services\Print\PrintDocumentStore;
use App\Services\Print\PrintJobSession;
use App\Services\Print\PrintTemplateCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class PrintTemplateController extends Controller
{
    public function __construct(
        private PrintTemplateCatalog $catalog,
        private PrintDocumentStore $documents,
        private PrintJobSession $session,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $this->session->captureKioskFromRequest($request);

        $data = $request->validate([
            'template' => ['required', 'string', Rule::in($this->catalog->ids())],
            'locale' => ['required', Rule::in(['ar', 'fr'])],
        ]);

        $template = $this->catalog->find($data['template']);
        abort_unless($template && is_file($this->catalog->path($template)), 404);

        $title = $data['locale'] === 'ar' ? $template['title_ar'] : $template['title_fr'];

        $job = PrintJob::query()->create([
            'locale' => $data['locale'],
            'status' => 'reviewing',
            'kiosk_id' => $this->session->kioskId(),
            'unit_price' => (float) config('print.template_price_per_page'),
            'duplex' => false,
        ]);

        try {
            $this->documents->addFromPath(
                $job,
                $this->catalog->path($template),
                $title.'.pdf',
                $data['locale'],
            );
        } catch (Throwable $exception) {
            $job->documents()->each(fn ($document) => $document->delete());
            $job->delete();

            throw $exception;
        }

        $this->session->remember($job);

        return redirect()->route('print.pay', $job);
    }
}
