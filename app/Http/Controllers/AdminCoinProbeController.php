<?php

namespace App\Http\Controllers;

use App\Models\CoinEvent;
use App\Services\Print\CoinCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCoinProbeController extends Controller
{
    public function __construct(private CoinCreditService $credits) {}

    public function show(Request $request): Response
    {
        $kioskId = (string) config('print.public_kiosk_id');

        return Inertia::render('Admin/Coins', [
            'email' => (string) ($request->user()?->email ?? ''),
            'role' => (string) ($request->user()?->role ?? 'admin'),
            'kiosk_id' => $kioskId,
            'probing' => $this->credits->isProbing($kioskId),
            'probe_until' => $this->credits->probeUntil($kioskId),
            'map' => collect(CoinCreditService::DINARS)
                ->map(fn (int $dinars, int $pulses) => [
                    'pulses' => $pulses,
                    'dinars' => $dinars,
                ])
                ->values()
                ->all(),
            'recent' => CoinEvent::query()
                ->where('kiosk_id', $kioskId)
                ->latest('id')
                ->limit(40)
                ->get(['id', 'pulses', 'dinars', 'print_job_id', 'event_key', 'created_at'])
                ->map(fn (CoinEvent $row) => [
                    'id' => $row->id,
                    'pulses' => (int) $row->pulses,
                    'dinars' => (int) $row->dinars,
                    'job_id' => $row->print_job_id,
                    'event_key' => $row->event_key,
                    'at' => optional($row->created_at)?->toIso8601String(),
                ])
                ->all(),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'minutes' => ['sometimes', 'integer', 'min:5', 'max:120'],
        ]);
        $minutes = (int) ($data['minutes'] ?? 30);

        $kioskId = (string) config('print.public_kiosk_id');
        $this->credits->startProbe($kioskId, $minutes);

        return back();
    }

    public function stop(): RedirectResponse
    {
        $this->credits->stopProbe((string) config('print.public_kiosk_id'));

        return back();
    }
}
