<?php

namespace App\Services\Kiosk;

use App\Events\ScreenUpdated;
use App\Models\KioskScreenSetting;
use App\Models\KioskVideo;
use Illuminate\Support\Facades\Storage;
use Throwable;

class KioskScreenCatalog
{
    public function settings(): KioskScreenSetting
    {
        return KioskScreenSetting::current();
    }

    /**
     * @return array{enabled: bool, idle_seconds: int, videos: list<array{id: int, url: string, bytes: int, sha256: string}>}
     */
    public function payload(): array
    {
        $settings = $this->settings();

        $videos = KioskVideo::query()
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (KioskVideo $video) => [
                'id' => $video->id,
                'url' => url('/kiosk/screen/videos/'.$video->id),
                'bytes' => (int) $video->bytes,
                'sha256' => $video->sha256,
            ])
            ->values()
            ->all();

        return [
            'enabled' => (bool) $settings->enabled,
            'idle_seconds' => (int) $settings->idle_seconds,
            'videos' => $videos,
        ];
    }

    /**
     * @return list<array{id: int, original_name: string, bytes: int, is_active: bool, position: int}>
     */
    public function adminVideos(): array
    {
        return KioskVideo::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (KioskVideo $video) => [
                'id' => $video->id,
                'original_name' => $video->original_name,
                'bytes' => (int) $video->bytes,
                'is_active' => (bool) $video->is_active,
                'position' => (int) $video->position,
            ])
            ->all();
    }

    public function reindex(): void
    {
        $rows = KioskVideo::query()->orderBy('position')->orderBy('id')->get();

        foreach ($rows as $index => $row) {
            $position = $index + 1;
            if ((int) $row->position !== $position) {
                $row->update(['position' => $position]);
            }
        }
    }

    public function deleteFile(KioskVideo $video): void
    {
        Storage::disk('public')->delete($video->path);
    }

    public function ping(): void
    {
        try {
            broadcast(new ScreenUpdated);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
