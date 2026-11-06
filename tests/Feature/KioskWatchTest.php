<?php

namespace Tests\Feature;

use App\Models\KioskPc;
use App\Models\PrintSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class KioskWatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_kiosk_can_post_health(): void
    {
        config(['print.kiosk_coin_token' => 'test-token']);
        $payload = [
            'printer' => 'up',
            'printer_detail' => 'idle',
            'keepalive' => 'up',
            'coin_agent' => 'up',
            'esp32' => 'up',
            'esp_detail' => 'serial open',
            'socket_process' => 'up',
            'spooler' => 'up',
            'pc_uptime' => '1d 2h',
            'disk_free_gb' => 40,
        ];

        $this->postJson('/kiosk/kiosk-metbati-01/health', $payload)->assertUnauthorized();
        $this->withHeader('X-Kiosk-Token', 'test-token')
            ->postJson('/kiosk/kiosk-metbati-01/health', $payload)
            ->assertOk();

        $pc = KioskPc::query()->where('kiosk_id', 'kiosk-metbati-01')->first();
        $this->assertNotNull($pc);
        $this->assertSame('up', $pc->health['printer']);
        $this->assertNotNull($pc->health_reported_at);
    }

    public function test_watch_edits_the_same_telegram_message(): void
    {
        config([
            'telegram.owner_id' => '1001',
            'telegram.status_bot_token' => 'status-token',
            'print.public_kiosk_id' => 'kiosk-metbati-01',
            'broadcasting.default' => 'log',
        ]);
        PrintSetting::query()->create([
            'toner_save' => false,
            'telegram_chat_id' => '1001',
            'status_message_id' => 77,
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 77],
            ]),
        ]);

        $this->artisan('kiosk:watch')->assertSuccessful();

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'editMessageText')
                && (int) $request['message_id'] === 77
                && (string) $request['chat_id'] === '1001'
                && str_contains((string) $request['text'], 'Metbati watch');
        });
        Http::assertSentCount(1);
    }

    public function test_status_bot_ignores_everyone_except_the_owner(): void
    {
        config([
            'telegram.owner_id' => '1001',
            'telegram.status_bot_token' => 'status-token',
            'telegram.status_webhook_secret' => 'status-hook',
        ]);
        Http::fake();

        $this->postJson('/telegram/status/nope', [
            'message' => ['text' => '/start', 'chat' => ['id' => 1001, 'type' => 'private']],
        ])->assertNotFound();

        $this->postJson('/telegram/status/status-hook', [
            'message' => ['text' => '/start', 'chat' => ['id' => 55, 'type' => 'private']],
        ])->assertOk();

        $this->postJson('/telegram/status/status-hook', [
            'message' => ['text' => '/start', 'chat' => ['id' => 1001, 'type' => 'group']],
        ])->assertOk();

        Http::assertNothingSent();
    }

    public function test_order_bot_ignores_a_chat_that_is_not_the_owner(): void
    {
        config([
            'telegram.owner_id' => '1001',
            'telegram.webhook_secret' => 'hook-secret',
            'telegram.bot_token' => 'test-token',
        ]);
        Http::fake();

        $this->postJson('/telegram/webhook/hook-secret', [
            'message' => ['text' => '/start', 'chat' => ['id' => 55, 'type' => 'private']],
        ])->assertOk();

        $this->assertNull(PrintSetting::query()->value('telegram_chat_id'));
        Http::assertNothingSent();
    }
}
