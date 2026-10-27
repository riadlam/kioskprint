<?php

namespace App\Http\Controllers;

use App\Models\PrintTemplate;
use App\Services\Print\PageCounter;
use App\Services\Print\PrintTemplateCatalog;
use App\Services\Print\UnreadablePrintFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminTemplateController extends Controller
{
    public function __construct(
        private PrintTemplateCatalog $catalog,
        private PageCounter $pages,
    ) {}

    public function show(): Response
    {
        return Inertia::render('Admin/Templates', [
            'email' => (string) config('app.admin_email'),
            'templatePrice' => (float) config('print.template_price_per_page'),
            'templates' => PrintTemplate::query()
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->map(fn (PrintTemplate $template) => [
                    'id' => $template->id,
                    'title_ar' => $template->title_ar,
                    'title_fr' => $template->title_fr,
                    'pages' => (int) $template->pages,
                ])
                ->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:255'],
            'title_fr' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:'.(int) config('print.max_kilobytes')],
        ]);

        $stored = $this->storePdf($request->file('file'));
        if ($stored === null) {
            return back()->withErrors([
                'file' => 'Upload a PDF, up to 12 MB.',
            ]);
        }

        PrintTemplate::query()->create([
            'slug' => $this->slug($data['title_fr']),
            'title_ar' => $data['title_ar'],
            'title_fr' => $data['title_fr'],
            'path' => $stored['path'],
            'pages' => $stored['pages'],
            'position' => (int) PrintTemplate::query()->max('position') + 1,
        ]);

        return back();
    }

    public function update(Request $request, PrintTemplate $template): RedirectResponse
    {
        $data = $request->validate([
            'title_ar' => ['required', 'string', 'max:255'],
            'title_fr' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:'.(int) config('print.max_kilobytes')],
        ]);

        $changes = [
            'title_ar' => $data['title_ar'],
            'title_fr' => $data['title_fr'],
        ];

        if ($request->hasFile('file')) {
            $stored = $this->storePdf($request->file('file'));
            if ($stored === null) {
                return back()->withErrors([
                    'file' => 'Upload a PDF, up to 12 MB.',
                ]);
            }
            $this->catalog->deleteFile($template);
            $changes['path'] = $stored['path'];
            $changes['pages'] = $stored['pages'];
        }

        $template->update($changes);

        return back();
    }

    public function destroy(PrintTemplate $template): RedirectResponse
    {
        $this->catalog->deleteFile($template);
        $template->delete();

        return back();
    }

    /**
     * @return array{path: string, pages: int}|null
     */
    private function storePdf(?UploadedFile $file): ?array
    {
        if (! $file instanceof UploadedFile) {
            return null;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());
        if ($extension !== 'pdf' || ! in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            return null;
        }

        try {
            $pages = $this->pages->count($file);
        } catch (UnreadablePrintFile) {
            return null;
        }

        $path = $file->storeAs('templates', Str::uuid()->toString().'.pdf', 'public');
        if ($path === false) {
            return null;
        }

        return [
            'path' => $path,
            'pages' => $pages,
        ];
    }

    private function slug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'form';
        }

        $slug = $base;
        $suffix = 2;
        while (PrintTemplate::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
