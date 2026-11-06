<?php

namespace App\Http\Controllers;

use App\Models\KioskPc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KioskHealthController extends Controller
{
    public function __invoke(Request $request, string $kiosk): JsonResponse
    {
        $token = (string) config('print.kiosk_coin_token');
        $given = (string) $request->header('X-Kiosk-Token', '');

        if ($token === '' || strlen($given) !== strlen($token) || ! hash_equals($token, $given)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'printer' => ['required', 'in:up,down,quiet'],
            'printer_detail' => ['nullable', 'string', 'max:180'],
            'keepalive' => ['required', 'in:up,down'],
            'coin_agent' => ['required', 'in:up,down'],
            'esp32' => ['required', 'in:up,down,quiet'],
            'esp_detail' => ['nullable', 'string', 'max:180'],
            'socket_process' => ['required', 'in:up,down'],
            'spooler' => ['required', 'in:up,down'],
            'pc_uptime' => ['nullable', 'string', 'max:40'],
            'disk_free_gb' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $pc = KioskPc::query()->firstOrNew(['kiosk_id' => $kiosk]);
        $pc->health = $data;
        $pc->health_reported_at = now();
        $pc->save();

        return response()->json(['ok' => true]);
    }
}
