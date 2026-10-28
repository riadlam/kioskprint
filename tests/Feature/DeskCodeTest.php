<?php

namespace Tests\Feature;

use App\Jobs\ExpireUnpaidPrint;
use App\Models\PrintJob;
use App\Services\Print\KioskDeskCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DeskCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plain_print_link_binds_the_public_kiosk(): void
    {
        config(['print.public_kiosk_id' => 'kiosk-metbati-01']);
        app(KioskDeskCodes::class)->publish('kiosk-metbati-01', '246');

        $this->get('/print')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Pin')
                ->where('kioskId', 'kiosk-metbati-01'));

        $this->post('/print/desk', ['code' => '246'])->assertRedirect(route('print.create'));
        $this->get('/print')->assertRedirect();

        $this->assertSame('kiosk-metbati-01', PrintJob::query()->value('kiosk_id'));
    }

    public function test_the_debug_code_opens_the_flow_and_leaves_the_screen_code(): void
    {
        config([
            'print.debug_desk_code' => '241',
            'print.public_kiosk_id' => 'kiosk-metbati-01',
        ]);
        app(KioskDeskCodes::class)->publish('kiosk-metbati-01', '246');

        $this->get('/print');
        $this->post('/print/desk', ['code' => '241'])->assertRedirect(route('print.create'));
        $this->get('/print')->assertRedirect();

        $this->assertSame('kiosk-metbati-01', PrintJob::query()->value('kiosk_id'));
        $this->assertTrue(app(KioskDeskCodes::class)->take('kiosk-metbati-01', '246'));
    }

    public function test_an_idle_phone_must_enter_a_new_code(): void
    {
        config(['print.session_minutes' => 10, 'print.public_kiosk_id' => 'kiosk-metbati-01']);
        app(KioskDeskCodes::class)->publish('kiosk-metbati-01', '246');

        $this->get('/print');
        $this->post('/print/desk', ['code' => '246'])->assertRedirect(route('print.create'));
        $this->get('/print')->assertRedirect();

        $this->travel(9)->minutes();
        $this->get('/print')->assertRedirect();

        $this->travel(11)->minutes();
        $this->get('/print')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Print/Pin'));
    }

    public function test_an_expired_job_link_returns_to_the_print_start(): void
    {
        config(['print.session_minutes' => 10, 'print.public_kiosk_id' => 'kiosk-metbati-01']);
        app(KioskDeskCodes::class)->publish('kiosk-metbati-01', '246');

        $this->get('/print');
        $this->post('/print/desk', ['code' => '246']);
        $this->get('/print')->assertRedirect();

        $job = \App\Models\PrintJob::query()->first();
        $this->assertNotNull($job);

        $this->travel(11)->minutes();

        $this->get('/print/'.$job->id)->assertRedirect('/print');
        $this->get('/print/'.$job->id.'/pay')->assertRedirect('/print');

        $this->get('/print')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Print/Pin'));
    }

    public function test_a_wrong_code_does_not_open_a_job(): void
    {
        app(KioskDeskCodes::class)->publish('kiosk-metbati-01', '246');

        $this->get('/print?kiosk=kiosk-metbati-01');

        $this->post('/print/desk', ['code' => '111'])
            ->assertRedirect()
            ->assertSessionHasErrors('code');

        $this->assertSame(0, PrintJob::query()->count());
    }

    public function test_only_the_tablet_can_publish_a_code(): void
    {
        config(['print.desk_token' => 'testing-desk-token']);

        $this->postJson('/kiosk/kiosk-metbati-01/desk', ['code' => '321'])
            ->assertUnauthorized();

        $this->postJson('/kiosk/kiosk-metbati-01/desk', ['code' => '321'], [
            'X-Kiosk-Desk' => 'testing-desk-token',
        ])->assertOk();

        $this->get('/print?kiosk=kiosk-metbati-01');
        $this->post('/print/desk', ['code' => '321'])->assertRedirect(route('print.create'));
        $this->get('/print?kiosk=kiosk-metbati-01')->assertRedirect();
        $this->assertSame(1, PrintJob::query()->count());
    }

    public function test_an_unpaid_coin_order_expires_without_taking_money(): void
    {
        Storage::fake('local');
        config(['print.simulate_monnayeur' => false, 'print.emulate_poste_paid' => false]);

        app(KioskDeskCodes::class)->publish('kiosk-metbati-01', '246');
        $this->get('/print?kiosk=kiosk-metbati-01');
        $this->post('/print/desk', ['code' => '246'])->assertRedirect(route('print.create'));
        $this->get('/print?kiosk=kiosk-metbati-01');

        $job = PrintJob::query()->first();
        $this->post('/print/'.$job->id.'/documents', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'monnayeur',
        ]);

        $job->refresh();
        $this->assertSame('awaiting_payment', $job->status);

        $this->travel(21)->seconds();
        (new ExpireUnpaidPrint($job->id))->handle();

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertEquals(0, (float) $job->amount_inserted);
    }
}
