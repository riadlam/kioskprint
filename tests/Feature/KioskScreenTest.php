<?php

namespace Tests\Feature;

use App\Events\ScreenUpdated;
use App\Models\KioskVideo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KioskScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tablet_feed_starts_empty_and_admin_routes_stay_locked(): void
    {
        $this->get('/kiosk/screen')
            ->assertOk()
            ->assertJson([
                'enabled' => true,
                'idle_seconds' => 60,
                'muted' => false,
                'videos' => [],
            ]);

        $this->get('/admin/screen')->assertRedirect('/admin');
        $this->post('/admin/screen/videos')->assertRedirect('/admin');
    }

    public function test_an_operator_can_upload_order_and_hide_videos(): void
    {
        Event::fake([ScreenUpdated::class]);
        Storage::fake('public');
        config([
            'app.admin_email' => 'admin@metbati.com',
            'app.admin_password' => 'secret',
        ]);
        $this->post('/admin/login', [
            'email' => 'admin@metbati.com',
            'password' => 'secret',
        ]);

        $first = UploadedFile::fake()->create('morning.mp4', 120, 'video/mp4');
        $second = UploadedFile::fake()->create('evening.mp4', 80, 'video/mp4');

        $this->put('/admin/screen', [
            'enabled' => true,
            'idle_seconds' => 45,
            'muted' => true,
        ])->assertRedirect();

        $this->post('/admin/screen/videos', ['video' => $first])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->post('/admin/screen/videos', ['video' => $second])->assertRedirect();

        $videos = KioskVideo::query()->orderBy('position')->get();
        $this->assertCount(2, $videos);
        $this->assertSame('morning.mp4', $videos[0]->original_name);
        $this->assertSame(100, (int) $videos[0]->volume_percent);
        $this->assertSame(64, strlen($videos[0]->sha256));
        Storage::disk('public')->assertExists($videos[0]->path);

        $this->post('/admin/screen/videos/'.$videos[1]->id.'/move', ['direction' => 'up'])
            ->assertRedirect();

        $ordered = KioskVideo::query()->orderBy('position')->pluck('original_name')->all();
        $this->assertSame(['evening.mp4', 'morning.mp4'], $ordered);

        $evening = KioskVideo::query()->where('original_name', 'evening.mp4')->first();
        $this->patch('/admin/screen/videos/'.$evening->id, ['volume_percent' => 35])
            ->assertRedirect();
        $this->assertSame(35, (int) $evening->fresh()->volume_percent);

        $hidden = KioskVideo::query()->where('original_name', 'morning.mp4')->first();
        $this->patch('/admin/screen/videos/'.$hidden->id, ['is_active' => false])
            ->assertRedirect();

        $feed = $this->getJson('/kiosk/screen')->assertOk()->json();
        $this->assertTrue($feed['enabled']);
        $this->assertSame(45, $feed['idle_seconds']);
        $this->assertTrue($feed['muted']);
        $this->assertCount(1, $feed['videos']);
        $this->assertSame('evening.mp4', KioskVideo::query()->find($feed['videos'][0]['id'])->original_name);
        $this->assertSame(35, $feed['videos'][0]['volume_percent']);
        $this->assertSame($videos->firstWhere('original_name', 'evening.mp4')->sha256, $feed['videos'][0]['sha256']);

        $this->get($feed['videos'][0]['url'])->assertOk();

        $this->delete('/admin/screen/videos/'.$hidden->id)->assertRedirect();

        $this->assertNull(KioskVideo::query()->find($hidden->id));
        Storage::disk('public')->assertMissing($hidden->path);
        Event::assertDispatched(ScreenUpdated::class);
    }

    public function test_a_non_mp4_upload_is_rejected(): void
    {
        Storage::fake('public');
        config([
            'app.admin_email' => 'admin@metbati.com',
            'app.admin_password' => 'secret',
        ]);
        $this->post('/admin/login', [
            'email' => 'admin@metbati.com',
            'password' => 'secret',
        ]);

        $this->post('/admin/screen/videos', [
            'video' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('video');

        $this->assertSame(0, KioskVideo::query()->count());
    }
}
