<?php

namespace Tests\Feature;

use App\Models\PrintJob;
use App\Services\Print\KioskDeskCodes;
use App\Services\Print\KioskPrinter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SofizPayCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_card_payment_under_75_is_refused(): void
    {
        Storage::fake('local');
        config([
            'print.emulate_poste_paid' => false,
            'services.sofizpay.merchant_account' => 'MERCHANT',
            'services.sofizpay.min_amount_dzd' => 75,
        ]);
        Http::fake();

        $job = $this->cardJob(20);

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'algerie_poste',
        ])->assertSessionHasErrors('payment_method');

        $job->refresh();
        $this->assertSame('reviewing', $job->status);
        Http::assertNothingSent();
    }

    public function test_a_verified_sofizpay_callback_starts_the_print(): void
    {
        Storage::fake('local');
        config([
            'print.emulate_poste_paid' => false,
            'services.sofizpay.merchant_account' => 'MERCHANT',
            'services.sofizpay.sandbox' => false,
            'queue.default' => 'sync',
        ]);

        Http::fake(function ($request) {
            if (str_contains($request->url(), 'cib-transaction-check')) {
                return Http::response([
                    'respCode' => '00',
                    'errorCode' => 0,
                    'orderStatus' => 2,
                    'Amount' => '80.00',
                    'destination_account' => 'MERCHANT',
                ]);
            }

            return Http::response([
                'success' => true,
                'transaction_id' => 'tx-1',
                'cib_transaction_id' => '2517039448',
                'payment_url' => 'https://cib.satim.dz/pay',
            ]);
        });

        $job = $this->cardJob(80);

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'algerie_poste',
        ])->assertRedirect('https://cib.satim.dz/pay');

        $job->refresh();
        $this->assertSame('awaiting_payment', $job->status);
        $this->assertSame('2517039448', $job->cib_order_number);
        $this->assertEquals(80, (float) $job->amount);

        $this->get('/print/pay/satim/return?eid='.urlencode(Crypt::encryptString($job->id)))
            ->assertRedirect(route('print.pay', $job));

        $job->refresh();
        $this->assertSame('printed', $job->status);
        $this->assertEquals(80, (float) $job->amount_inserted);
        $this->assertCount(1, app(KioskPrinter::class)->units);
    }

    public function test_a_wrong_amount_or_merchant_does_not_start_the_print(): void
    {
        Storage::fake('local');
        config([
            'print.emulate_poste_paid' => false,
            'services.sofizpay.merchant_account' => 'MERCHANT',
        ]);

        $job = $this->cardJob(80);
        $job->update([
            'status' => 'awaiting_payment',
            'payment_method' => 'algerie_poste',
            'amount' => 80,
            'cib_order_number' => '2517039448',
        ]);

        Http::fake([
            'sofizpay.com/*' => Http::response([
                'respCode' => '00',
                'errorCode' => 0,
                'orderStatus' => 2,
                'Amount' => '10.00',
                'destination_account' => 'MERCHANT',
            ]),
        ]);

        $this->get('/print/pay/satim/return?eid='.urlencode(Crypt::encryptString($job->id)))
            ->assertRedirect(route('print.pay', $job))
            ->assertSessionHas('payment_error', 'unconfirmed');

        $this->assertSame('reviewing', $job->fresh()->status);

        $job->update([
            'status' => 'awaiting_payment',
            'payment_method' => 'algerie_poste',
        ]);

        Http::fake([
            'sofizpay.com/*' => Http::response([
                'respCode' => '00',
                'errorCode' => 0,
                'orderStatus' => 2,
                'Amount' => '80.00',
                'destination_account' => 'SOMEONE_ELSE',
            ]),
        ]);

        $this->get('/print/pay/satim/return?eid='.urlencode(Crypt::encryptString($job->id)))
            ->assertRedirect(route('print.pay', $job))
            ->assertSessionHas('payment_error', 'unconfirmed');

        $this->assertSame('reviewing', $job->fresh()->status);
        $this->assertSame([], app(KioskPrinter::class)->units);
    }

    public function test_bank_failures_open_a_reason_on_the_payment_page(): void
    {
        Storage::fake('local');
        config([
            'print.emulate_poste_paid' => false,
            'services.sofizpay.merchant_account' => 'MERCHANT',
        ]);

        $cases = [
            [['respCode' => '17', 'errorCode' => 0, 'orderStatus' => 3], 'canceled'],
            [['respCode' => '51', 'errorCode' => 5, 'orderStatus' => 6], 'insufficient'],
            [['respCode' => '54', 'errorCode' => 5, 'orderStatus' => 6], 'expired'],
            [['respCode' => '55', 'errorCode' => 5, 'orderStatus' => 6], 'pin'],
            [['respCode' => '61', 'errorCode' => 5, 'orderStatus' => 6], 'limit'],
            [['respCode' => '14', 'errorCode' => 5, 'orderStatus' => 6], 'invalid_card'],
            [['respCode' => '91', 'errorCode' => 5, 'orderStatus' => 6], 'unavailable'],
            [['respCode' => '05', 'errorCode' => 5, 'orderStatus' => 6], 'declined'],
            [['respCode' => '00', 'errorCode' => 0, 'orderStatus' => 0], 'not_completed'],
        ];

        $job = $this->cardJob(80);
        $payload = [];
        Http::fake(function () use (&$payload) {
            return Http::response($payload);
        });

        foreach ($cases as [$next, $reason]) {
            $payload = $next;
            $job->update([
                'status' => 'awaiting_payment',
                'payment_method' => 'algerie_poste',
                'amount' => 80,
                'amount_inserted' => 0,
                'cib_order_number' => '2517039448',
            ]);

            $this->get('/print/pay/satim/return?eid='.urlencode(Crypt::encryptString($job->id)))
                ->assertRedirect(route('print.pay', $job))
                ->assertSessionHas('payment_error', $reason);

            $this->assertSame('reviewing', $job->fresh()->status);
        }

        $this->assertSame([], app(KioskPrinter::class)->units);
    }

    private function cardJob(int $unitPrice): PrintJob
    {
        app(KioskDeskCodes::class)->publish('kiosk-satim', '246');
        $this->get('/print?kiosk=kiosk-satim');
        $this->post('/print/desk', ['code' => '246']);
        $this->get('/print?kiosk=kiosk-satim');

        $job = PrintJob::query()->first();
        $this->assertNotNull($job);

        $this->post('/print/'.$job->id.'/documents', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('page.jpg')],
        ]);

        $job->update(['unit_price' => $unitPrice]);

        return $job->fresh();
    }
}
