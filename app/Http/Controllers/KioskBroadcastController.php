<?php

namespace App\Http\Controllers;

use App\Services\Print\KioskPrintRelay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pusher\Pusher;
use Throwable;

class KioskBroadcastController extends Controller
{
    public function __construct(private KioskPrintRelay $relay) {}

    public function __invoke(Request $request): JsonResponse
    {
        $token = (string) config('print.kiosk_coin_token');
        $given = (string) $request->header('X-Kiosk-Token', '');

        if ($token === '' || strlen($given) !== strlen($token) || ! hash_equals($token, $given)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:200'],
            'kiosk_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{4,64}$/'],
        ]);

        $channel = $data['channel_name'];
        $kioskId = $data['kiosk_id'];
        $private = 'private-kiosk-pc.'.$kioskId;

        if ($channel !== 'presence-kiosk-pcs' && $channel !== $private) {
            return response()->json(['message' => 'Unknown channel'], 403);
        }

        try {
            $signed = $channel === 'presence-kiosk-pcs'
                ? $this->pusher()->authorizePresenceChannel($channel, $data['socket_id'], $kioskId, [
                    'name' => 'Mini PC',
                ])
                : $this->pusher()->authorizeChannel($channel, $data['socket_id']);
        } catch (Throwable) {
            return response()->json(['message' => 'The socket could not be signed'], 422);
        }

        if ($channel === $private) {
            app()->terminating(function () use ($kioskId) {
                $this->relay->releasePending($kioskId);
            });
        }

        return response()->json(json_decode($signed, true));
    }

    private function pusher(): Pusher
    {
        $config = config('broadcasting.connections.reverb');

        return new Pusher(
            (string) $config['key'],
            (string) $config['secret'],
            (string) $config['app_id'],
        );
    }
}
