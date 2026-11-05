<?php

namespace App\Http\Controllers;

use App\Models\PrintCode;
use App\Services\Print\PageCounter;
use App\Services\Print\PrintCodePinGenerator;
use App\Services\Print\UnreadablePrintFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AdminPrintCodeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $codes = PrintCode::query()
            ->when($user->isProfessor(), fn ($q) => $q->where('user_id', $user->id))
            ->with('user:id,name,email')
            ->latest()
            ->get()
            ->map(fn (PrintCode $code) => $this->serialize($code));

        return Inertia::render('Admin/PrintCodes/Index', [
            'codes' => $codes,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }

    public function create(): RedirectResponse
    {
        return redirect()->route('admin.print-codes.index');
    }

    public function store(Request $request, PageCounter $pages, PrintCodePinGenerator $pins): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'file' => ['required', 'file', 'max:'.(int) config('print.max_kilobytes', 12288)],
        ]);

        /** @var UploadedFile $file */
        $file = $data['file'];
        $this->assertAllowedFile($file);

        try {
            $pageCount = $pages->count($file);
        } catch (UnreadablePrintFile) {
            throw ValidationException::withMessages([
                'file' => 'تعذر قراءة الملف. استخدم PDF أو صورة بدون كلمة مرور.',
            ]);
        }

        $pin = $pins->unique();
        $path = $file->store('print-codes/'.$request->user()->id, 'local');

        $code = PrintCode::query()->create([
            'user_id' => $request->user()->id,
            'title' => trim($data['title']),
            'pin' => $pin,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/pdf',
            'page_count' => $pageCount,
            'unit_price' => (float) config('print.price_per_page', 20),
            'currency' => 'DA',
            'is_active' => true,
            'print_count' => 0,
        ]);

        return redirect()
            ->route('admin.print-codes.index')
            ->with('success', 'تم إنشاء رمز الطباعة.')
            ->with('shared_code', $this->serialize($code));
    }

    public function show(Request $request, PrintCode $printCode): RedirectResponse
    {
        $this->authorizeCode($request, $printCode);

        return redirect()
            ->route('admin.print-codes.index')
            ->with('shared_code', $this->serialize($printCode));
    }

    public function edit(Request $request, PrintCode $printCode): RedirectResponse
    {
        $this->authorizeCode($request, $printCode);

        return redirect()->route('admin.print-codes.index');
    }

    public function update(Request $request, PrintCode $printCode, PageCounter $pages): RedirectResponse
    {
        $this->authorizeCode($request, $printCode);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'file' => ['nullable', 'file', 'max:'.(int) config('print.max_kilobytes', 12288)],
            'regenerate_pin' => ['sometimes', 'boolean'],
        ]);

        $printCode->title = trim($data['title']);

        if ($request->boolean('regenerate_pin')) {
            $printCode->pin = app(PrintCodePinGenerator::class)->unique();
        }

        if ($request->hasFile('file')) {
            /** @var UploadedFile $file */
            $file = $request->file('file');
            $this->assertAllowedFile($file);

            try {
                $pageCount = $pages->count($file);
            } catch (UnreadablePrintFile) {
                throw ValidationException::withMessages([
                    'file' => 'تعذر قراءة الملف. استخدم PDF أو صورة بدون كلمة مرور.',
                ]);
            }

            $old = $printCode->path;
            $path = $file->store('print-codes/'.$request->user()->id, 'local');
            $printCode->fill([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'page_count' => $pageCount,
            ]);

            if ($old && $old !== $path) {
                Storage::disk('local')->delete($old);
            }
        }

        $printCode->save();

        return redirect()
            ->route('admin.print-codes.index')
            ->with('success', 'تم تحديث المستند.')
            ->with('shared_code', $this->serialize($printCode->fresh()));
    }

    public function destroy(Request $request, PrintCode $printCode): RedirectResponse
    {
        $this->authorizeCode($request, $printCode);
        $printCode->releasePin();
        $printCode->delete();

        return redirect()
            ->route('admin.print-codes.index')
            ->with('success', 'تم حذف رمز الطباعة.');
    }

    private function authorizeCode(Request $request, PrintCode $code): void
    {
        $user = $request->user();
        if ($user->isAdmin()) {
            return;
        }

        abort_unless($user->id === $code->user_id, 403);
    }

    private function assertAllowedFile(UploadedFile $file): void
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $allowed = array_map('strtolower', config('print.extensions', ['pdf']));

        if (! in_array($ext, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'الصيغة غير مدعومة. استخدم PDF أو صورة.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(PrintCode $code): array
    {
        return [
            'id' => $code->id,
            'title' => $code->title,
            'pin' => $code->pin,
            'original_name' => $code->original_name,
            'page_count' => (int) $code->page_count,
            'unit_price' => (float) $code->unit_price,
            'amount' => $code->amountDue(),
            'currency' => $code->currency,
            'is_active' => (bool) $code->is_active,
            'print_count' => (int) $code->print_count,
            'professor' => $code->relationLoaded('user') && $code->user
                ? ['name' => $code->user->name, 'email' => $code->user->email]
                : null,
            'created_at' => optional($code->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            'updated_at' => optional($code->updated_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
        ];
    }
}
