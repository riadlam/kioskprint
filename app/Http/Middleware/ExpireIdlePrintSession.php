<?php

namespace App\Http\Middleware;

use App\Services\Print\PrintJobSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExpireIdlePrintSession
{
    public function __construct(private PrintJobSession $session) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->session->expireIfIdle();

        return $next($request);
    }
}
