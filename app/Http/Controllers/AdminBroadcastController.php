<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pusher\Pusher;
use Throwable;

class AdminBroadcastController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if ($request->session()->get('admin_ok') !== true) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'socket_id' => ['required', 'string', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:200'],
        ]);

        $channel = $data['channel_name'];

        if (! in_array($channel, ['presence-kiosk-pcs', 'private-admin-kiosks'], true)) {
            return response()->json(['message' => 'Unknown channel'], 403);
        }

        try {
            $signed = $channel === 'presence-kiosk-pcs'
                ? $this->pusher()->authorizePresenceChannel($channel, $data['socket_id'], 'desk', [
                    'name' => 'Desk',
                ])
                : $this->pusher()->authorizeChannel($channel, $data['socket_id']);
        } catch (Throwable) {
            return response()->json(['message' => 'The socket could not be signed'], 422);
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
