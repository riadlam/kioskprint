<?php

namespace App\Http\Controllers;

use App\Models\KioskVideo;
use App\Services\Kiosk\KioskScreenCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KioskScreenController extends Controller
{
    public function __construct(private KioskScreenCatalog $catalog) {}

    public function show(Request $request): JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json(null, 204, $this->cors());
        }

        return response()->json($this->catalog->payload(), 200, $this->cors());
    }

    public function video(Request $request, KioskVideo $video): BinaryFileResponse|JsonResponse
    {
        if ($request->isMethod('OPTIONS')) {
            return response()->json(null, 204, $this->cors());
        }

        abort_unless($video->is_active, 404);

        $path = Storage::disk('public')->path($video->path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => 'video/mp4',
            'Access-Control-Allow-Origin' => '*',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function cors(): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type',
        ];
    }
}
