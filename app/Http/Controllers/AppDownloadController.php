<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AppDownloadController extends Controller
{
    public function show(): View
    {
        $path = $this->apkPath();
        $ready = is_file($path);

        return view('app-download', [
            'ready' => $ready,
            'bytes' => $ready ? (int) filesize($path) : 0,
            'updatedAt' => $ready ? date('Y-m-d H:i', (int) filemtime($path)) : null,
            'build' => $ready ? (int) filemtime($path) : 0,
        ]);
    }

    public function file(): BinaryFileResponse|Response
    {
        $path = $this->apkPath();
        abort_unless(is_file($path), 404);

        return response()->download($path, 'metbati.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    private function apkPath(): string
    {
        return storage_path('app/apk/metbati.apk');
    }
}
