<?php

namespace App\Http\Controllers;

use App\Models\KioskPc;
use App\Models\KioskPrintUnit;
use App\Services\Print\KioskPrintRelay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KioskPrintUnitController extends Controller
{
    public function __construct(private KioskPrintRelay $relay) {}

    public function ready(Request $request, string $kiosk): JsonResponse
    {
        $this->token($request);
        $this->kioskId($kiosk);

        KioskPc::query()->firstOrNew(['kiosk_id' => $kiosk])->markConnected();
        $this->relay->releasePending($kiosk);

        return response()->json(['ok' => true]);
    }

    public function file(string $kiosk, KioskPrintUnit $unit): BinaryFileResponse
    {
        abort_unless($unit->kiosk_id === $kiosk && $unit->state === 'pending', 404);

        $absolute = Storage::disk('local')->path($unit->path);
        abort_unless(is_file($absolute), 404);

        return response()->file($absolute, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function result(Request $request, string $kiosk, KioskPrintUnit $unit): JsonResponse
    {
        $this->token($request);
        $this->kioskId($kiosk);

        $data = $request->validate([
            'ok' => ['required', 'boolean'],
            'error' => ['nullable', 'string', 'max:500'],
        ]);

        KioskPc::query()->where('kiosk_id', $kiosk)->update(['last_seen_at' => now()]);

        return response()->json($this->relay->finish(
            $unit,
            $kiosk,
            (bool) $data['ok'],
            trim((string) ($data['error'] ?? '')),
        ));
    }

    private function token(Request $request): void
    {
        $token = (string) config('print.kiosk_coin_token');
        $given = (string) $request->header('X-Kiosk-Token', '');

        abort_unless(
            $token !== '' && strlen($given) === strlen($token) && hash_equals($token, $given),
            401,
        );
    }

    private function kioskId(string $kiosk): void
    {
        abort_unless(preg_match('/^[A-Za-z0-9_-]{4,64}$/', $kiosk) === 1, 404);
    }
}
