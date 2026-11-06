<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\PrintSetting;
use App\Models\User;
use App\Services\Print\PrintPriceList;
use App\Services\Admin\EnsureBootstrapAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function show(Request $request): Response
    {
        app(EnsureBootstrapAdmin::class)();

        if (! $request->user()) {
            return Inertia::render('Admin/Dashboard', [
                'authed' => false,
            ]);
        }

        $user = $request->user();

        if ($user->isProfessor()) {
            return redirect()->route('admin.print-codes.index');
        }

        return Inertia::render('Admin/Dashboard', [
            'authed' => true,
            'email' => $user->email,
            'role' => $user->role,
            'summary' => $this->summary(),
            'jobs' => $this->jobs(),
            'tonerSave' => PrintSetting::current()->toner_save,
            'priceBands' => app(PrintPriceList::class)->bands()->map(fn ($band) => [
                'id' => $band->id,
                'min_pages' => $band->min_pages,
                'max_pages' => $band->max_pages,
                'price_per_page' => (float) $band->price_per_page,
            ])->values(),
        ]);
    }

    public function updatePrintSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'toner_save' => ['required', 'boolean'],
        ]);

        PrintSetting::current()->update([
            'toner_save' => (bool) $data['toner_save'],
        ]);

        return back();
    }

    public function updatePriceBands(Request $request, PrintPriceList $prices): RedirectResponse
    {
        $data = $request->validate([
            'bands' => ['required', 'array', 'min:1'],
            'bands.*.id' => ['required', 'integer'],
            'bands.*.min_pages' => ['required', 'integer', 'min:1', 'max:10000'],
            'bands.*.max_pages' => ['required', 'integer', 'min:1', 'max:10000'],
            'bands.*.price_per_page' => ['required', 'numeric', 'min:1', 'max:10000'],
        ]);

        $prices->replace($data['bands']);

        return back();
    }

    public function login(Request $request): RedirectResponse
    {
        app(EnsureBootstrapAdmin::class)();

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = strtolower(trim($data['email']));

        if (! Auth::attempt(['email' => $email, 'password' => $data['password']], false)) {
            return back()->withErrors([
                'email' => 'Those details do not match.',
            ]);
        }

        $user = $request->user();

        if (! $user || ! $user->is_active || ! in_array($user->role, [User::ROLE_ADMIN, User::ROLE_PROFESSOR], true)) {
            Auth::logout();

            return back()->withErrors([
                'email' => 'Those details do not match.',
            ]);
        }

        $request->session()->regenerate();

        if ($user->isProfessor()) {
            return redirect()->route('admin.print-codes.index');
        }

        return redirect()->route('admin');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin');
    }

    /**
     * @return array{jobs: int, printed: int, failed: int, open: int, revenue: float}
     */
    private function summary(): array
    {
        $byStatus = PrintJob::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'jobs' => (int) $byStatus->sum(),
            'printed' => (int) ($byStatus['printed'] ?? 0),
            'failed' => (int) ($byStatus['failed'] ?? 0),
            'open' => (int) $byStatus->except(['printed', 'failed'])->sum(),
            'revenue' => (float) PrintJob::query()->where('status', 'printed')->sum('amount'),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jobs(): array
    {
        return PrintJob::query()
            ->with(['documents:id,print_job_id,original_name'])
            ->latest()
            ->limit(40)
            ->get()
            ->map(fn (PrintJob $job) => [
                'id' => $job->id,
                'status' => $job->status,
                'kiosk_id' => $job->kiosk_id,
                'title' => $job->title,
                'pages' => (int) $job->page_count,
                'amount' => $job->amount !== null ? (float) $job->amount : null,
                'currency' => $job->currency,
                'payment_method' => $job->payment_method,
                'files' => $job->documents->pluck('original_name')->all(),
                'created_at' => optional($job->created_at)->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ])
            ->all();
    }
}
