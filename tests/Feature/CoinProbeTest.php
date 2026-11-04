<?php

namespace Tests\Feature;

use App\Events\KioskCoinSeen;
use App\Models\CoinEvent;
use App\Services\Print\CoinCreditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class CoinProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_opens_the_cash_window_without_a_job(): void
    {
        config(['print.kiosk_coin_token' => 'test-token']);

        app(CoinCreditService::class)->startProbe('kiosk-metbati-01', 30);

        $this->withHeader('X-Kiosk-Token', 'test-token')
            ->getJson('/kiosk/kiosk-metbati-01/cash')
            ->assertOk()
            ->assertJson([
                'open' => true,
                'probe' => true,
                'max_pulses' => 6,
            ]);
    }

    public function test_probe_coin_is_broadcast_with_pulses_and_map(): void
    {
        Event::fake([KioskCoinSeen::class]);
        config(['print.kiosk_coin_token' => 'test-token']);

        app(CoinCreditService::class)->startProbe('kiosk-metbati-01', 30);

        $this->withHeader('X-Kiosk-Token', 'test-token')
            ->postJson('/kiosk/kiosk-metbati-01/coins', [
                'pulses' => 4,
                'event_key' => 'probe-50da-aaaa',
                'returned' => false,
            ])
            ->assertOk()
            ->assertJson([
                'result' => 'probe',
            ]);

        $this->assertDatabaseHas('coin_events', [
            'kiosk_id' => 'kiosk-metbati-01',
            'pulses' => 4,
            'dinars' => 50,
            'event_key' => 'probe-50da-aaaa',
        ]);

        Event::assertDispatched(KioskCoinSeen::class, function (KioskCoinSeen $event) {
            return $event->payload['pulses'] === 4
                && $event->payload['dinars'] === 50
                && $event->payload['acceptor'] === 'coin'
                && $event->payload['result'] === 'probe';
        });
    }

    public function test_unknown_pulse_count_is_broadcast_as_unknown(): void
    {
        Event::fake([KioskCoinSeen::class]);
        config(['print.kiosk_coin_token' => 'test-token']);

        $this->withHeader('X-Kiosk-Token', 'test-token')
            ->postJson('/kiosk/kiosk-metbati-01/coins', [
                'pulses' => 9,
                'event_key' => 'probe-bad-pulse1',
                'returned' => false,
            ])
            ->assertOk()
            ->assertJson(['result' => 'unknown']);

        $this->assertSame(0, CoinEvent::query()->count());

        Event::assertDispatched(KioskCoinSeen::class, function (KioskCoinSeen $event) {
            return $event->payload['pulses'] === 9
                && $event->payload['dinars'] === null
                && $event->payload['result'] === 'unknown';
        });
    }

    public function test_admin_can_open_the_coin_probe_page(): void
    {
        config([
            'app.admin_email' => 'ops@metbati.test',
            'app.admin_password' => 'secret',
            'print.public_kiosk_id' => 'kiosk-metbati-01',
        ]);

        $this->withSession(['admin_ok' => true])
            ->get('/admin/coins')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Coins')
                ->where('kiosk_id', 'kiosk-metbati-01')
                ->where('probing', false));

        $this->withSession(['admin_ok' => true])
            ->post('/admin/coins/start', ['minutes' => 30])
            ->assertRedirect();

        $this->assertTrue(app(CoinCreditService::class)->isProbing('kiosk-metbati-01'));
    }
}
