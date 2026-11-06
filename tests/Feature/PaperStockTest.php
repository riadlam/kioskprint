<?php

namespace Tests\Feature;

use App\Models\KioskPrintUnit;
use App\Models\PaperStock;
use App\Models\PrintJob;
use App\Models\PrintSetting;
use App\Services\Print\KioskPrintRelay;
use App\Services\Print\PaperTray;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaperStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_owner_can_load_paper_and_ask_what_is_left(): void
    {
        config([
            'telegram.webhook_secret' => 'hook-secret',
            'telegram.bot_token' => 'test-token',
            'telegram.owner_id' => '1001',
        ]);
        PrintSetting::query()->create([
            'toner_save' => false,
            'telegram_chat_id' => '1001',
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 9]]),
        ]);

        $this->postJson('/telegram/webhook/hook-secret', [
            'message' => ['text' => '/add 200', 'chat' => ['id' => 55, 'type' => 'private']],
        ])->assertOk();

        $this->assertSame(0, (int) PaperStock::current()->sheets_left);
        Http::assertNothingSent();

        $this->postJson('/telegram/webhook/hook-secret', [
            'message' => ['text' => '/add 200', 'chat' => ['id' => 1001, 'type' => 'private']],
        ])->assertOk();

        $this->postJson('/telegram/webhook/hook-secret', [
            'message' => ['text' => '/left', 'chat' => ['id' => 1001, 'type' => 'private']],
        ])->assertOk();

        $this->assertSame(200, (int) PaperStock::current()->sheets_left);
        Http::assertSent(function ($request) {
            return str_contains((string) $request['text'], 'Added 200 sheets');
        });
        Http::assertSent(function ($request) {
            return str_contains((string) $request['text'], '200 sheets left');
        });
    }

    public function test_a_printed_sheet_is_deducted_once_and_a_low_tray_warns_once(): void
    {
        Event::fake();
        config([
            'telegram.bot_token' => 'test-token',
            'telegram.owner_id' => '1001',
        ]);
        PrintSetting::query()->create([
            'toner_save' => false,
            'telegram_chat_id' => '1001',
        ]);
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 4]]),
        ]);

        app(PaperTray::class)->add(5);

        $job = PrintJob::query()->create([
            'id' => (string) Str::uuid(),
            'kiosk_id' => 'kiosk-metbati-01',
            'status' => 'printing',
            'page_count' => 1,
        ]);
        $unit = KioskPrintUnit::query()->create([
            'id' => (string) Str::uuid(),
            'print_job_id' => $job->id,
            'kiosk_id' => 'kiosk-metbati-01',
            'position' => 1,
            'page_ids' => [1],
            'path' => 'print-spool/'.$job->id.'/unit-1.pdf',
            'duplex' => true,
            'state' => 'pending',
        ]);

        $relay = app(KioskPrintRelay::class);
        $relay->finish($unit, 'kiosk-metbati-01', true, '');
        $relay->finish($unit->fresh(), 'kiosk-metbati-01', true, '');

        $this->assertSame(4, (int) PaperStock::current()->sheets_left);
        Http::assertSent(function ($request) {
            $text = (string) $request['text'];

            return str_contains($text, 'Time to refill')
                && str_contains($text, 'started with 5 sheets')
                && str_contains($text, 'Used 1 sheet')
                && str_contains($text, '4 sheets left');
        });
        Http::assertSentCount(1);
    }
}
