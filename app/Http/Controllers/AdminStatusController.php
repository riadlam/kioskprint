<?php

namespace App\Http\Controllers;

use App\Services\Admin\FloorStatus;
use Inertia\Inertia;
use Inertia\Response;

class AdminStatusController extends Controller
{
    public function __construct(private FloorStatus $floor) {}

    public function show(): Response
    {
        return Inertia::render('Admin/Status', [
            'email' => (string) config('app.admin_email'),
            ...$this->floor->report(),
        ]);
    }
}
