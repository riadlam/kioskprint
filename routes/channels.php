<?php

/*
| Kiosk tablets use public channels: kiosk.{kioskId} and kiosk-screen
| (see App\Events\Print*). No auth callback required for public channels.
|
| The mini PC and the admin desk sign presence-kiosk-pcs and their private
| channels through their own auth routes. These callbacks guard the default
| /broadcasting/auth path.
*/

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('kiosk-pcs', function ($user) {
    if (! $user || ! $user->is_active || ! $user->isAdmin()) {
        return false;
    }

    return ['id' => (string) $user->id, 'name' => $user->name];
});

Broadcast::channel('admin-kiosks', function ($user) {
    return $user && $user->is_active && $user->isAdmin();
});
