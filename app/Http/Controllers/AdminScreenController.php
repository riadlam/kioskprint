<?php

namespace App\Http\Controllers;

use App\Models\KioskVideo;
use App\Services\Kiosk\KioskScreenCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminScreenController extends Controller
{
    public function __construct(private KioskScreenCatalog $catalog) {}

    public function show(): Response
    {
        $settings = $this->catalog->settings();

        return Inertia::render('Admin/Screen', [
            'email' => (string) config('app.admin_email'),
            'settings' => [
                'enabled' => (bool) $settings->enabled,
                'idle_seconds' => (int) $settings->idle_seconds,
            ],
            'videos' => $this->catalog->adminVideos(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'idle_seconds' => ['required', 'integer', 'min:15', 'max:3600'],
        ]);

        $this->catalog->settings()->update([
            'enabled' => $request->boolean('enabled'),
            'idle_seconds' => (int) $data['idle_seconds'],
        ]);
        $this->catalog->ping();

        return back();
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'video' => ['required', 'file', 'max:81920'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('video');
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = (string) $file->getMimeType();

        if ($extension !== 'mp4' || ! in_array($mime, ['video/mp4', 'application/mp4'], true)) {
            return back()->withErrors([
                'video' => 'Upload an MP4 video, up to 80 MB.',
            ]);
        }

        $path = $file->storeAs('attract', Str::uuid()->toString().'.mp4', 'public');
        if ($path === false) {
            return back()->withErrors([
                'video' => 'The video could not be saved.',
            ]);
        }

        $absolute = Storage::disk('public')->path($path);
        $next = (int) KioskVideo::query()->max('position') + 1;

        KioskVideo::query()->create([
            'position' => $next,
            'original_name' => $file->getClientOriginalName(),
            'path' => $path,
            'bytes' => (int) filesize($absolute),
            'sha256' => hash_file('sha256', $absolute),
            'is_active' => true,
        ]);

        $this->catalog->reindex();
        $this->catalog->ping();

        return back();
    }

    public function updateVideo(Request $request, KioskVideo $video): RedirectResponse
    {
        $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $video->update([
            'is_active' => $request->boolean('is_active'),
        ]);
        $this->catalog->ping();

        return back();
    }

    public function move(Request $request, KioskVideo $video): RedirectResponse
    {
        $data = $request->validate([
            'direction' => ['required', 'in:up,down'],
        ]);

        $ordered = KioskVideo::query()->orderBy('position')->orderBy('id')->get()->values();
        $index = $ordered->search(fn (KioskVideo $row) => $row->id === $video->id);
        $swapIndex = $data['direction'] === 'up' ? $index - 1 : $index + 1;

        if ($index !== false && $swapIndex >= 0 && $swapIndex < $ordered->count()) {
            $other = $ordered[$swapIndex];
            $position = $video->position;
            $video->update(['position' => $other->position]);
            $other->update(['position' => $position]);
        }

        $this->catalog->reindex();
        $this->catalog->ping();

        return back();
    }

    public function destroy(KioskVideo $video): RedirectResponse
    {
        $this->catalog->deleteFile($video);
        $video->delete();
        $this->catalog->reindex();
        $this->catalog->ping();

        return back();
    }
}
