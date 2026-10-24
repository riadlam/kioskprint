<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

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
            'health' => $this->health(),
            'jobs' => $this->jobs(),
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $expected = (string) config('app.admin_password');
        $given = (string) $request->input('password', '');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            return back()->withErrors([
                'password' => 'Wrong password.',
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
     * @return list<array{name: string, ok: bool, detail: string}>
     */
    private function health(): array
    {
        $checks = [];

        try {
            DB::select('select 1 as ok');
            $checks[] = ['name' => 'Database', 'ok' => true, 'detail' => 'MySQL is answering'];
        } catch (Throwable) {
            $checks[] = ['name' => 'Database', 'ok' => false, 'detail' => 'MySQL did not answer'];
        }

        $writable = is_writable(storage_path('app'));
        $checks[] = [
            'name' => 'Storage',
            'ok' => $writable,
            'detail' => $writable ? 'Print files can be saved' : 'Storage is not writable',
        ];

        $pending = Schema::hasTable('jobs') ? DB::table('jobs')->count() : 0;
        $failed = Schema::hasTable('failed_jobs') ? DB::table('failed_jobs')->count() : 0;
        $checks[] = [
            'name' => 'Queue',
            'ok' => $failed === 0,
            'detail' => $pending.' waiting, '.$failed.' failed',
        ];

        $port = (int) config('reverb.servers.reverb.port', 8080);
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.4);
        $live = is_resource($socket);
        if ($live) {
            fclose($socket);
        }
        $checks[] = [
            'name' => 'Live updates',
            'ok' => $live,
            'detail' => $live ? 'Reverb is listening on port '.$port : 'Reverb is not listening on port '.$port,
        ];

        return $checks;
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
