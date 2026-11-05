<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            Auth::logout();

            return redirect()->route('admin');
        }

        if (! in_array($user->role, ['admin', 'professor'], true)) {
            Auth::logout();

            return redirect()->route('admin');
        }

        return $next($request);
    }
}
