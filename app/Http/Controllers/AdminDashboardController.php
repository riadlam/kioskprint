<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\PrintSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function show(Request $request): Response
    {
        if (! $request->session()->get('admin_ok')) {
            return Inertia::render('Admin/Dashboard', [
                'authed' => false,
            ]);
        }

        return Inertia::render('Admin/Dashboard', [
            'authed' => true,
            'email' => (string) config('app.admin_email'),
            'summary' => $this->summary(),
            'jobs' => $this->jobs(),
            'tonerSave' => PrintSetting::current()->toner_save,
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

    public function login(Request $request): RedirectResponse
    {
        $email = strtolower(trim((string) $request->input('email', '')));
        $password = (string) $request->input('password', '');
        $expectedEmail = strtolower(trim((string) config('app.admin_email')));
        $expectedPassword = (string) config('app.admin_password');

        $matches = $expectedEmail !== ''
            && $expectedPassword !== ''
            && strlen($email) === strlen($expectedEmail)
            && strlen($password) === strlen($expectedPassword)
            && hash_equals($expectedEmail, $email)
            && hash_equals($expectedPassword, $password);

        if (! $matches) {
            return back()->withErrors([
                'email' => 'Those details do not match.',
            ]);
        }

        $request->session()->regenerate();
        $request->session()->put('admin_ok', true);

        return redirect()->route('admin');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('admin_ok');

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
