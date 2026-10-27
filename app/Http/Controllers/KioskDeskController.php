<?php

namespace App\Http\Controllers;

use App\Events\KioskDeskRotated;
use App\Services\Print\KioskDeskCodes;
use App\Services\Print\PrintJobSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class KioskDeskController extends Controller
{
    public function __construct(
        private KioskDeskCodes $codes,
        private PrintJobSession $session,
    ) {}

    public function publish(Request $request, string $kiosk): JsonResponse
    {
        $token = (string) config('print.desk_token');
        $given = (string) $request->header('X-Kiosk-Desk', '');

        if ($token === '' || strlen($given) !== strlen($token) || ! hash_equals($token, $given)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! preg_match('/^[A-Za-z0-9_-]{4,64}$/', $kiosk)) {
            return response()->json(['message' => 'Unknown kiosk'], 404);
        }

        $data = $request->validate([
            'code' => ['required', 'regex:/^\d{3}$/'],
        ]);

        $this->codes->publish($kiosk, $data['code']);

        return response()->json(['ok' => true]);
    }

    public function claim(Request $request): RedirectResponse
    {
        $this->session->captureKioskFromRequest($request);
        $kioskId = $this->session->kioskId();

        if ($kioskId === null) {
            return redirect()->route('print.create');
        }

        $data = $request->validate([
            'code' => ['required', 'regex:/^\d{3}$/'],
        ]);

        $debug = (string) config('print.debug_desk_code');
        if ($debug !== '' && strlen($debug) === strlen($data['code']) && hash_equals($debug, $data['code'])) {
            $this->session->grantDesk($kioskId);

            return redirect()->route('print.create');
        }

        $limit = 'desk-guess:'.$request->session()->getId();

        if (RateLimiter::tooManyAttempts($limit, 8)) {
            return back()->withErrors([
                'code' => 'limited',
            ]);
        }

        if (! $this->codes->take($kioskId, $data['code'])) {
            RateLimiter::hit($limit, 600);

            return back()->withErrors([
                'code' => 'wrong',
            ]);
        }

        RateLimiter::clear($limit);
        $this->session->grantDesk($kioskId);
        $this->broadcastRotated($kioskId);

        return redirect()->route('print.create');
    }

    private function broadcastRotated(string $kioskId): void
    {
        try {
            broadcast(new KioskDeskRotated($kioskId));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
