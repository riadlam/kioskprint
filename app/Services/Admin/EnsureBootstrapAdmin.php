<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class EnsureBootstrapAdmin
{
    public function __invoke(): void
    {
        $email = strtolower(trim((string) config('app.admin_email')));
        $password = (string) config('app.admin_password');

        if ($email === '' || $password === '') {
            return;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user) {
            if ($user->role !== User::ROLE_ADMIN || ! $user->is_active) {
                $user->forceFill([
                    'role' => User::ROLE_ADMIN,
                    'is_active' => true,
                ])->save();
            }

            return;
        }

        User::query()->create([
            'name' => 'Operator',
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);
    }
}
