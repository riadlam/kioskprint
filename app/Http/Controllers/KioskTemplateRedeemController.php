<?php

namespace App\Http\Controllers;

use App\Services\Print\TemplateRedeemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class KioskTemplateRedeemController extends Controller
{
    public function __invoke(Request $request, string $kiosk, TemplateRedeemService $redeemer): JsonResponse
    {
        if (! $this->deskAuthorized($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if (! preg_match('/^[A-Za-z0-9_-]{4,64}$/', $kiosk)) {
            return response()->json(['message' => 'Unknown kiosk'], 404);
        }

        $data = $request->validate([
            'template' => ['required', 'string', 'max:120'],
            'locale' => ['sometimes', 'in:ar,fr'],
        ]);

        try {
            $payload = $redeemer->redeem(
                $kiosk,
                $data['template'],
                $data['locale'] ?? 'ar',
                'template-redeem:'.$kiosk.':'.$request->ip(),
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Invalid template';

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
