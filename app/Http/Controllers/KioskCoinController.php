<?php

namespace App\Http\Controllers;

use App\Services\Print\CoinCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskCoinController extends Controller
{
    public function __construct(private CoinCreditService $credits) {}

    public function __invoke(Request $request, string $kiosk): JsonResponse
    {
        if (! $this->allowed($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'pulses' => ['required', 'integer', 'min:0', 'max:30'],
            'event_key' => ['required', 'string', 'regex:/^[A-Za-z0-9:_-]{8,80}$/'],
            'returned' => ['sometimes', 'boolean'],
        ]);

        return response()->json(
            $this->credits->credit($kiosk, (int) $data['pulses'], $data['event_key'], (bool) ($data['returned'] ?? false)),
        );
    }

    public function window(Request $request, string $kiosk): JsonResponse
    {
        if (! $this->allowed($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json($this->credits->window($kiosk));
    }

    private function allowed(Request $request): bool
    {
        $token = (string) config('print.kiosk_coin_token');
        $given = (string) $request->header('X-Kiosk-Token', '');

        return $token !== '' && strlen($given) === strlen($token) && hash_equals($token, $given);
    }
}
