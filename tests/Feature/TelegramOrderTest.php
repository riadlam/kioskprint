<?php

namespace Tests\Feature;

use App\Models\PrintJob;
use App\Models\PrintSetting;
use App\Services\Print\KioskDeskCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TelegramOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_telegram_message_follows_the_order_until_it_is_paid(): void
    {
        Storage::fake('local');
        config([
            'print.public_kiosk_id' => 'kiosk-metbati-01',
            'print.simulate_monnayeur' => false,
            'print.kiosk_coin_token' => 'test-token',
            'telegram.bot_token' => 'test-token',
        ]);
        PrintSetting::query()->create([
            'toner_save' => false,
            'telegram_chat_id' => '1001',
        ]);
        app(KioskDeskCodes::class)->publish('kiosk-metbati-01', '246');

        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 42],
            ]),
        ]);

        $this->get('/print');
        $this->post('/print/desk', ['code' => '246'])->assertRedirect();
        $this->get('/print')->assertRedirect();

        $job = PrintJob::query()->first();
        $this->assertNotNull($job);
        $this->assertSame(42, $job->telegram_message_id);

        $this->post('/print/'.$job->id.'/documents', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('page.jpg')],
        ]);

        $this->get(route('print.pay', $job))->assertOk();
        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'monnayeur',
        ])->assertRedirect();

        $this->withHeader('X-Kiosk-Token', 'test-token')
            ->postJson('/kiosk/kiosk-metbati-01/coins', [
                'pulses' => 4,
                'event_key' => 'coin-over-50aa',
            ])
            ->assertOk()
            ->assertJson(['result' => 'paid']);

        $texts = Http::recorded()
            ->map(fn (array $pair) => (string) ($pair[0]->data()['text'] ?? ''))
            ->all();

        $this->assertSame('Status: pin_code', $texts[0]);
        $this->assertStringContainsString('Status: pending_payment_1', $texts[1]);
        $this->assertStringContainsString('Pages: 1', $texts[1]);
        $this->assertStringContainsString('Amount: 20 DA', $texts[1]);
        $this->assertStringContainsString('Status: pending_payment_2', $texts[2]);
        $this->assertStringContainsString('Method: Monnayeur', $texts[2]);
        $this->assertStringContainsString('Status: complete', $texts[3]);
        $this->assertStringContainsString('Charged: 20 DA', $texts[3]);
        $this->assertStringContainsString('Paid: 50 DA', $texts[3]);
        $this->assertStringContainsString('Extra: 30 DA', $texts[3]);
        $this->assertSame(42, $job->fresh()->telegram_message_id);
    }

    public function test_the_first_start_message_saves_the_chat(): void
    {
        config(['telegram.webhook_secret' => 'hook-secret', 'telegram.bot_token' => 'test-token']);
        Http::fake([
            'api.telegram.org/*' => Http::response([
                'ok' => true,
                'result' => ['message_id' => 7],
            ]),
        ]);

        $this->postJson('/telegram/webhook/wrong', [
            'message' => ['text' => '/start', 'chat' => ['id' => 55]],
        ])->assertNotFound();

        $this->postJson('/telegram/webhook/hook-secret', [
            'message' => ['text' => '/start', 'chat' => ['id' => 55]],
        ])->assertOk();

        $this->assertSame('55', PrintSetting::current()->telegram_chat_id);

        $this->postJson('/telegram/webhook/hook-secret', [
            'message' => ['text' => '/start', 'chat' => ['id' => 99]],
        ])->assertOk();

        $this->assertSame('55', PrintSetting::query()->value('telegram_chat_id'));
        Http::assertSentCount(1);
    }
}
