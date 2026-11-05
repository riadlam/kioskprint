<?php

namespace App\Http\Controllers;

use App\Services\Print\PrintCodeRedeemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KioskPrintCodeController extends Controller
{
    public function redeem(Request $request, string $kiosk, PrintCodeRedeemService $redeemer): JsonResponse
    {
        if (! $this->deskAuthorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! preg_match('/^[A-Za-z0-9_-]{4,64}$/', $kiosk)) {
            return response()->json(['message' => 'Unknown kiosk'], 404);
        }

        $data = $request->validate([
            'pin' => ['required', 'regex:/^\d{4}$/'],
        ]);

        try {
            $payload = $redeemer->redeem(
                $kiosk,
                $data['pin'],
                'print-code:'.$kiosk.':'.$request->ip(),
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Invalid pin';

            return response()->json([
                'message' => $message,
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json($payload);
    }

    private function deskAuthorized(Request $request): bool
    {
        $token = (string) config('print.desk_token');
        $given = (string) $request->header('X-Kiosk-Desk', '');

        return $token !== '' && strlen($given) === strlen($token) && hash_equals($token, $given);
    }
}
