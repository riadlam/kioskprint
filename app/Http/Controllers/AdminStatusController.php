<?php

namespace App\Http\Controllers;

use App\Services\Admin\FloorStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStatusController extends Controller
{
    public function __construct(private FloorStatus $floor) {}

    public function show(Request $request): Response
    {
        return Inertia::render('Admin/Status', [
            'email' => (string) ($request->user()?->email ?? ''),
            'role' => (string) ($request->user()?->role ?? 'admin'),
            ...$this->floor->report(),
        ]);
    }
}
