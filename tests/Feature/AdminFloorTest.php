<?php

namespace Tests\Feature;

use App\Models\CoinEvent;
use App\Models\PrintSetting;
use App\Models\KioskPc;
use App\Models\KioskPrintUnit;
use App\Models\PrintJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminFloorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.admin_email' => 'admin@metbati.com',
            'app.admin_password' => 'secret',
        ]);
    }

    public function test_toner_saver_stays_off_until_an_admin_turns_it_on(): void
    {
        $this->post('/admin/print-settings', ['toner_save' => true])->assertRedirect(route('admin'));

        $this->post('/admin/login', [
            'email' => 'admin@metbati.com',
            'password' => 'secret',
        ]);

        $this->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tonerSave', false));

        $this->post('/admin/print-settings', ['toner_save' => true])->assertRedirect();
        $this->assertTrue(PrintSetting::current()->toner_save);

        $this->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('tonerSave', true));
    }

    public function test_status_keeps_a_successful_print_resting_and_retires_old_kiosks(): void
    {
        $this->post('/admin/login', [
            'email' => 'admin@metbati.com',
            'password' => 'secret',
        ]);

        $live = KioskPc::query()->create([
            'kiosk_id' => 'kiosk-metbati-01',
            'last_seen_at' => now()->subHour(),
            'connected_since' => now()->subHours(3),
        ]);
        $live->forceFill([
            'updated_at' => now(),
        ])->save();

        PrintJob::query()->create([
            'locale' => 'ar',
            'status' => 'printed',
            'kiosk_id' => 'kiosk-old-install',
            'page_count' => 1,
            'amount' => 20,
            'currency' => 'DA',
        ]);

        $job = PrintJob::query()->create([
            'locale' => 'ar',
            'status' => 'printed',
            'kiosk_id' => 'kiosk-metbati-01',
            'page_count' => 1,
            'amount' => 20,
            'currency' => 'DA',
        ]);

        KioskPrintUnit::query()->create([
            'print_job_id' => $job->id,
            'kiosk_id' => 'kiosk-metbati-01',
            'position' => 1,
            'page_ids' => [1],
            'path' => 'print-spool/'.$job->id.'/unit.pdf',
            'duplex' => false,
            'state' => 'printed',
        ]);

        CoinEvent::query()->create([
            'print_job_id' => $job->id,
            'kiosk_id' => 'kiosk-metbati-01',
            'pulses' => 3,
            'dinars' => 20,
            'event_key' => 'coin-floor-test-1',
        ]);

        DB::table('kiosk_print_units')->where('kiosk_id', 'kiosk-metbati-01')->update([
            'updated_at' => now()->subHour(),
        ]);
        DB::table('coin_events')->where('event_key', 'coin-floor-test-1')->update([
            'created_at' => now()->subHour(),
        ]);

        if (Schema::hasTable('failed_jobs')) {
            DB::table('failed_jobs')->insert([
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'old',
                'failed_at' => now(),
            ]);
        }

        $this->get('/admin/status')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Status')
                ->where('signals', function ($signals) {
                    $byKey = collect($signals)->keyBy('key');

                    return $byKey['database']['state'] === 'up'
                        && $byKey['printer']['state'] === 'quiet'
                        && str_contains($byKey['printer']['headline'], 'Resting')
                        && $byKey['queue']['state'] === 'quiet'
                        && $byKey['coin']['state'] === 'quiet';
                })
                ->where('machines', function ($machines) {
                    $rows = collect($machines)->keyBy('kiosk_id');

                    return $rows['kiosk-metbati-01']['live'] === true
                        && $rows['kiosk-metbati-01']['state'] === 'quiet'
                        && $rows['kiosk-old-install']['live'] === false
                        && $rows['kiosk-old-install']['state'] === 'quiet';
                }));
    }

    public function test_status_requires_the_desk_session(): void
    {
        $this->get('/admin/status')->assertRedirect('/admin');
    }
}
