<?php

namespace Tests\Feature;

use App\Services\Print\KioskDeskCodes;
use Database\Seeders\PrintTemplateSeeder;
use App\Models\CoinEvent;
use App\Models\PrintDocument;
use App\Models\PrintJob;
use App\Models\PrintPage;
use App\Services\Print\FakeKioskPrinter;
use App\Services\Print\KioskPrinter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrintFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_print_starts_on_review(): void
    {
        $response = $this->get('/print');

        $job = PrintJob::query()->first();
        $this->assertNotNull($job);

        $response->assertRedirect(route('print.review', $job));

        $this->get(route('print.review', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Review')
                ->where('job.total_pages', 0));
    }

    public function test_image_upload_opens_a_one_page_review(): void
    {
        Storage::fake('local');

        $response = $this->post('/print', [
            'locale' => 'fr',
            'files' => [UploadedFile::fake()->image('photo.jpg', 80, 80)],
        ]);

        $job = PrintJob::query()->first();
        $this->assertNotNull($job);
        $this->assertSame(1, $job->pages()->count());

        $response->assertRedirect(route('print.review', $job));

        $this->get(route('print.review', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Review')
                ->where('job.total_pages', 1)
                ->where('job.locale', 'fr'));
    }

    public function test_a_page_can_be_removed(): void
    {
        Storage::fake('local');

        $this->post('/print', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $page = PrintPage::query()->first();
        $job = PrintJob::query()->first();

        $this->delete(route('print.pages.destroy', [$job, $page]))
            ->assertRedirect(route('print.review', $job));

        $this->assertSame(0, PrintPage::query()->count());
        $this->assertSame(0, PrintDocument::query()->count());
    }

    public function test_unsupported_files_are_rejected(): void
    {
        $this->post('/print', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->create('notes.txt', 20, 'text/plain')],
        ])->assertSessionHasErrors('files.0');

        $this->assertSame(0, PrintJob::query()->count());
    }

    public function test_another_session_cannot_open_the_job(): void
    {
        Storage::fake('local');

        $this->post('/print', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $job = PrintJob::query()->first();

        $this->withSession(['print_job_ids' => []])
            ->get(route('print.review', $job))
            ->assertRedirect(route('print.create'));
    }

    public function test_payment_page_shows_quote_and_accepts_method(): void
    {
        Storage::fake('local');

        $this->post('/print', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $job = PrintJob::query()->first();

        $this->get(route('print.pay', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Payment')
                ->where('quote.page_count', 1)
                ->where('quote.amount', 20));

        config(['print.emulate_poste_paid' => false]);

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'algerie_poste',
        ])->assertRedirect(route('print.pay', $job));

        $job->refresh();
        $this->assertSame('algerie_poste', $job->payment_method);
        $this->assertSame('awaiting_payment', $job->status);
        $this->assertEquals(20, (float) $job->amount);
    }

    public function test_print_create_captures_kiosk_id(): void
    {
        $this->get('/print?kiosk=kiosk-demo-1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Print/Pin'));

        $this->assertSame(0, PrintJob::query()->count());

        $this->openAtKiosk('kiosk-demo-1');

        $job = PrintJob::query()->first();
        $this->assertNotNull($job);
        $this->assertSame('kiosk-demo-1', $job->kiosk_id);
    }

    public function test_algerie_poste_emulate_marks_paid_and_queues_print(): void
    {
        Storage::fake('local');
        config(['print.emulate_poste_paid' => true, 'queue.default' => 'sync']);

        $this->openAtKiosk('kiosk-demo-2');
        $job = PrintJob::query()->first();

        $this->post('/print/'.$job->id.'/documents', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $job->refresh();

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'algerie_poste',
        ])->assertRedirect(route('print.pay', $job));

        $job->refresh();
        $this->assertSame('printed', $job->status);
        $this->assertNotNull($job->paid_at);
    }

    public function test_files_stay_on_the_phone_until_payment(): void
    {
        Storage::fake('local');

        $this->openAtKiosk('kiosk-demo-hold');
        $job = PrintJob::query()->first();
        $this->assertSame(0, $job->documents()->count());

        $this->post(route('print.hold', $job), [
            'locale' => 'ar',
            'duplex' => true,
            'files' => [
                UploadedFile::fake()->image('first.jpg'),
                UploadedFile::fake()->image('second.jpg'),
            ],
            'selection' => ['1:1', '0:1'],
        ])->assertRedirect(route('print.pay', $job));

        $job->refresh();
        $this->assertTrue($job->duplex);
        $this->assertSame(2, $job->pages()->count());
        $this->assertSame(0, $job->documents()->count() - 2);

        $names = $job->pages()->with('document')->get()
            ->map(fn ($page) => $page->document->original_name)
            ->all();
        $this->assertSame(['second.jpg', 'first.jpg'], $names);

        $this->get(route('print.pay', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Payment')
                ->where('quote.page_count', 2)
                ->where('quote.amount', 40));
    }

    public function test_checkout_saves_order_and_duplex_then_opens_payment(): void
    {
        Storage::fake('local');

        $this->post('/print', [
            'locale' => 'ar',
            'files' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
            ],
        ]);

        $job = PrintJob::query()->first();
        $pageIds = $job->pages()->pluck('print_pages.id')->all();
        $this->assertCount(2, $pageIds);

        $reversed = array_reverse($pageIds);

        $this->post(route('print.checkout', $job), [
            'page_ids' => $reversed,
            'duplex' => true,
        ])->assertRedirect(route('print.pay', $job));

        $job->refresh();

        $this->assertTrue($job->duplex);
        $this->assertSame(
            array_map('intval', $reversed),
            $job->pages()->pluck('print_pages.id')->map(fn ($id) => (int) $id)->all()
        );
    }

    public function test_monnayeur_status_reports_inserted_amount(): void
    {
        Storage::fake('local');
        config(['print.simulate_monnayeur' => false]);

        $this->post('/print', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $job = PrintJob::query()->first();
        $job->update(['kiosk_id' => 'kiosk-coin-read']);

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'monnayeur',
        ])->assertRedirect(route('print.pay', $job));

        $job->update(['amount_inserted' => 10]);

        $this->getJson(route('print.pay.status', $job))
            ->assertOk()
            ->assertJson([
                'payment_method' => 'monnayeur',
                'required' => 20,
                'inserted' => 10,
                'remaining' => 10,
                'complete' => false,
            ]);
    }

    public function test_poste_prints_pages_in_saved_order_once(): void
    {
        Storage::fake('local');
        config(['print.emulate_poste_paid' => true]);

        $job = $this->jobWithImages('kiosk-order', ['a.jpg', 'b.jpg', 'c.jpg']);
        $original = $this->pageIds($job);
        $reversed = array_reverse($original);

        $this->post(route('print.checkout', $job), [
            'page_ids' => $reversed,
            'duplex' => false,
        ])->assertRedirect(route('print.pay', $job));

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'algerie_poste',
        ])->assertRedirect(route('print.pay', $job));

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'algerie_poste',
        ])->assertRedirect(route('print.pay', $job));

        $job->refresh();
        $printer = $this->printer();

        $this->assertSame('printed', $job->status);
        $this->assertSame(3, $job->printed_pages);
        $this->assertSame($reversed, $this->printedIds($printer));
        $this->get(route('kiosk.jobs.show', ['kiosk' => 'kiosk-order', 'job' => $job]))
            ->assertOk()
            ->assertJsonPath('status', 'printed')
            ->assertJsonPath('current_page', 3)
            ->assertJsonPath('percent', 100);
        $this->assertCount(3, $printer->units);
        $this->assertFalse($printer->units[0]['duplex']);
        $this->assertSame([], Storage::disk('local')->allFiles('print-spool'));
    }

    public function test_duplex_prints_page_pairs_without_duplicates(): void
    {
        Storage::fake('local');
        config(['print.emulate_poste_paid' => true]);

        $job = $this->jobWithImages('kiosk-duplex', ['a.jpg', 'b.jpg', 'c.jpg']);
        $ids = $this->pageIds($job);

        $this->post(route('print.checkout', $job), [
            'page_ids' => $ids,
            'duplex' => true,
        ])->assertRedirect(route('print.pay', $job));

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'algerie_poste',
        ]);

        $printer = $this->printer();
        $flat = $this->printedIds($printer);

        $this->assertCount(2, $printer->units);
        $this->assertSame(array_slice($ids, 0, 2), $printer->units[0]['page_ids']);
        $this->assertSame([$ids[2]], $printer->units[1]['page_ids']);
        $this->assertTrue($printer->units[0]['duplex']);
        $this->assertTrue($printer->units[1]['duplex']);
        $this->assertSame($ids, $flat);
        $this->assertSame($ids, array_values(array_unique($flat)));
        $this->assertSame([], Storage::disk('local')->allFiles('print-spool'));
    }

    public function test_printer_failure_does_not_mark_job_printed_or_leave_spool_files(): void
    {
        Storage::fake('local');
        config(['print.emulate_poste_paid' => true]);

        $job = $this->jobWithImages('kiosk-fail', ['a.jpg']);
        $this->printer()->fail = true;

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'algerie_poste',
        ])->assertRedirect(route('print.pay', $job));

        $job->refresh();

        $this->assertSame('failed', $job->status);
        $this->assertSame([], $this->printer()->units);
        $this->assertSame([], Storage::disk('local')->allFiles('print-spool'));
    }

    public function test_poste_without_kiosk_does_not_print(): void
    {
        Storage::fake('local');
        config(['print.emulate_poste_paid' => true]);

        $this->post('/print', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $job = PrintJob::query()->first();

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'algerie_poste',
        ])->assertSessionHasErrors('payment_method');

        $job->refresh();
        $this->assertSame('reviewing', $job->status);
        $this->assertSame([], $this->printer()->units);
    }

    public function test_template_opens_payment_at_fifty_dinars_per_page(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $this->seed(PrintTemplateSeeder::class);

        $this->openAtKiosk('kiosk-template-1');

        $this->get(route('print.review', PrintJob::query()->first()))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('templatePrice', 50)
                ->has('templates', 31)
                ->where('templates.0.id', 'recours-rejet-demande-administrative')
                ->where('templates.0.title_ar', 'نموذج طعن في رفض طلب إداري')
                ->where('templates.0.pages', 1));

        $this->post('/print/templates', [
            'template' => 'plainte-pour-vol',
            'locale' => 'fr',
        ])->assertRedirect(route('print.pay', PrintJob::query()->whereNotNull('unit_price')->first()));

        $job = PrintJob::query()->whereNotNull('unit_price')->first();
        $this->assertNotNull($job);
        $this->assertSame('kiosk-template-1', $job->kiosk_id);
        $this->assertSame('fr', $job->locale);
        $this->assertEquals(50, (float) $job->unit_price);
        $this->assertSame(2, $job->pages()->count());
        $this->assertStringNotContainsString('Servicedz', (string) $job->documents()->value('original_name'));

        $this->get(route('print.pay', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Payment')
                ->where('quote.page_count', 2)
                ->where('quote.unit_price', 50)
                ->where('quote.amount', 100));
    }

    public function test_coins_of_100_50_and_50_pay_a_200_job_once(): void
    {
        Storage::fake('local');
        config([
            'print.simulate_monnayeur' => false,
            'print.kiosk_coin_token' => 'test-token',
        ]);

        $job = $this->openCashJob('kiosk-coins', 200);

        $this->coin('kiosk-coins', 5, 'coin-100-aaaa')->assertOk()->assertJson([
            'result' => 'credited',
            'inserted' => 100,
            'remaining' => 100,
            'complete' => false,
        ]);
        $this->coin('kiosk-coins', 4, 'coin-50-aaaab')->assertOk()->assertJson([
            'inserted' => 150,
            'remaining' => 50,
        ]);
        $this->coin('kiosk-coins', 4, 'coin-50-aaaac')->assertOk()->assertJson([
            'result' => 'paid',
            'inserted' => 200,
            'remaining' => 0,
            'complete' => true,
        ]);

        $job->refresh();
        $this->assertSame('printed', $job->status);
        $this->assertCount(1, $this->printer()->units);
        $this->assertSame(3, CoinEvent::query()->count());
    }

    public function test_the_same_coin_event_is_not_added_twice(): void
    {
        Storage::fake('local');
        config([
            'print.simulate_monnayeur' => false,
            'print.kiosk_coin_token' => 'test-token',
        ]);

        $this->openCashJob('kiosk-once', 200);
        $this->coin('kiosk-once', 5, 'coin-same-key1');
        $this->coin('kiosk-once', 5, 'coin-same-key1')->assertJson([
            'result' => 'replay',
            'inserted' => 100,
        ]);

        $job = PrintJob::query()->first();
        $this->assertSame(100, (int) round((float) $job->amount_inserted));
        $this->assertSame(1, CoinEvent::query()->count());
        $this->assertSame('awaiting_payment', $job->status);
    }

    public function test_unknown_pulse_count_is_ignored(): void
    {
        Storage::fake('local');
        config([
            'print.simulate_monnayeur' => false,
            'print.kiosk_coin_token' => 'test-token',
        ]);

        $job = $this->openCashJob('kiosk-bad', 50);
        $this->coin('kiosk-bad', 9, 'coin-bad-pulses')->assertOk()->assertJson(['result' => 'ignored']);

        $job->refresh();
        $this->assertSame(0, (int) round((float) $job->amount_inserted));
        $this->assertSame(0, CoinEvent::query()->count());
    }

    public function test_a_coin_with_no_open_job_is_ignored_and_not_replayed_later(): void
    {
        Storage::fake('local');
        config([
            'print.simulate_monnayeur' => false,
            'print.kiosk_coin_token' => 'test-token',
        ]);

        $this->coin('kiosk-idle', 4, 'coin-before-job')->assertOk()->assertJson(['result' => 'ignored']);

        $job = $this->openCashJob('kiosk-idle', 50);
        $this->coin('kiosk-idle', 4, 'coin-before-job')->assertJson(['result' => 'replay']);

        $job->refresh();
        $this->assertSame('awaiting_payment', $job->status);
        $this->assertSame(0, (int) round((float) $job->amount_inserted));
    }

    public function test_coins_after_payment_are_ignored(): void
    {
        Storage::fake('local');
        config([
            'print.simulate_monnayeur' => false,
            'print.kiosk_coin_token' => 'test-token',
        ]);

        $job = $this->openCashJob('kiosk-done', 50);
        $this->coin('kiosk-done', 4, 'coin-exact-50aa');
        $this->coin('kiosk-done', 1, 'coin-extra-5aaa')->assertJson(['result' => 'ignored']);

        $job->refresh();
        $this->assertSame(50, (int) round((float) $job->amount_inserted));
        $this->assertCount(1, $this->printer()->units);
    }

    public function test_locked_credits_sum_and_print_once_when_the_last_coin_is_larger(): void
    {
        Storage::fake('local');
        config([
            'print.simulate_monnayeur' => false,
            'print.kiosk_coin_token' => 'test-token',
        ]);

        $job = $this->openCashJob('kiosk-over', 150);
        $this->coin('kiosk-over', 5, 'coin-over-100aa');
        $this->coin('kiosk-over', 5, 'coin-over-100bb');

        $job->refresh();
        $this->assertSame('printed', $job->status);
        $this->assertSame(200, (int) round((float) $job->amount_inserted));
        $this->assertCount(1, $this->printer()->units);

        $this->getJson(route('print.pay.status', $job))
            ->assertOk()
            ->assertJson([
                'remaining' => 0,
                'complete' => true,
                'status' => 'printed',
            ]);
    }

    public function test_a_new_coin_session_replaces_an_empty_one_and_prints_when_paid(): void
    {
        Storage::fake('local');
        config([
            'print.simulate_monnayeur' => false,
            'print.kiosk_coin_token' => 'test-token',
        ]);

        $first = $this->openCashJob('kiosk-next', 20);
        $second = $this->anotherReview($first->kiosk_id, ['next.jpg']);
        $second->update(['unit_price' => 20]);
        $this->post(route('print.pay.store', $second), [
            'payment_method' => 'monnayeur',
        ])->assertRedirect();

        $first->refresh();
        $second->refresh();
        $this->assertSame('failed', $first->status);
        $this->assertSame('awaiting_payment', $second->status);

        $this->coin('kiosk-next', 3, 'coin-new-session')->assertOk()->assertJson([
            'result' => 'paid',
            'job_id' => $second->id,
            'inserted' => 20,
        ]);

        $second->refresh();
        $this->assertSame('printed', $second->status);
    }

    public function test_coins_already_taken_keep_that_session(): void
    {
        Storage::fake('local');
        config([
            'print.simulate_monnayeur' => false,
            'print.kiosk_coin_token' => 'test-token',
        ]);

        $first = $this->openCashJob('kiosk-hold', 50);
        $this->coin('kiosk-hold', 3, 'hold-20da');

        $second = $this->anotherReview($first->kiosk_id, ['other.jpg']);
        $second->update(['unit_price' => 20]);

        $this->post(route('print.pay.store', $second), [
            'payment_method' => 'monnayeur',
        ])->assertSessionHasErrors('payment_method');

        $first->refresh();
        $this->assertSame('awaiting_payment', $first->status);
        $this->assertSame(20, (int) round((float) $first->amount_inserted));
    }

    public function test_payment_status_does_not_mark_a_job_paid_by_itself(): void
    {
        Storage::fake('local');
        config(['print.simulate_monnayeur' => false]);

        $job = $this->openCashJob('kiosk-poll', 20);
        $job->update(['amount_inserted' => 20]);

        $this->getJson(route('print.pay.status', $job))
            ->assertOk()
            ->assertJson([
                'inserted' => 20,
                'remaining' => 0,
                'complete' => false,
                'status' => 'awaiting_payment',
            ]);

        $job->refresh();
        $this->assertSame('awaiting_payment', $job->status);
        $this->assertSame([], $this->printer()->units);
    }

    public function test_admin_dashboard_requires_email_and_password_and_lists_jobs(): void
    {
        config([
            'app.admin_email' => 'admin@metbati.com',
            'app.admin_password' => 'secret',
        ]);

        $this->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('authed', false)
                ->missing('jobs'));

        $this->post('/admin/login', [
            'email' => 'admin@metbati.com',
            'password' => 'nope',
        ])->assertSessionHasErrors('email');

        PrintJob::query()->create([
            'locale' => 'ar',
            'status' => 'printed',
            'kiosk_id' => 'kiosk-admin',
            'page_count' => 2,
            'amount' => 40,
            'currency' => 'DA',
        ]);

        $this->post('/admin/login', [
            'email' => 'Admin@Metbati.com',
            'password' => 'secret',
        ])->assertRedirect('/admin');

        $this->get('/admin')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Dashboard')
                ->where('authed', true)
                ->where('email', 'admin@metbati.com')
                ->missing('health')
                ->missing('kiosks')
                ->where('summary.printed', 1)
                ->where('jobs.0.kiosk_id', 'kiosk-admin')
                ->where('jobs.0.status', 'printed'));
    }

    public function test_coin_route_rejects_a_missing_token(): void
    {
        config(['print.kiosk_coin_token' => 'test-token']);

        $this->postJson('/kiosk/kiosk-x/coins', [
            'pulses' => 1,
            'event_key' => 'coin-no-token1',
        ])->assertUnauthorized();
    }

    private function openAtKiosk(string $kioskId): void
    {
        app(KioskDeskCodes::class)->publish($kioskId, '246');
        $this->get('/print?kiosk='.$kioskId)->assertOk();
        $this->post('/print/desk', ['code' => '246'])->assertRedirect(route('print.create'));
        $this->get('/print?kiosk='.$kioskId)->assertRedirect();
    }

    /**
     * @param  list<string>  $names
     */
    private function jobWithImages(string $kioskId, array $names): PrintJob
    {
        $this->openAtKiosk($kioskId);
        $job = PrintJob::query()->first();

        $this->post('/print/'.$job->id.'/documents', [
            'locale' => 'ar',
            'files' => array_map(
                fn (string $name) => UploadedFile::fake()->image($name),
                $names,
            ),
        ]);

        return $job->fresh();
    }

    /**
     * @return list<int>
     */
    private function pageIds(PrintJob $job): array
    {
        return $job->pages()->pluck('print_pages.id')->map(fn ($id) => (int) $id)->all();
    }

    private function anotherReview(string $kioskId, array $names): PrintJob
    {
        $this->openAtKiosk($kioskId);
        $job = PrintJob::query()->where('status', 'reviewing')->latest()->first();
        $this->assertNotNull($job);

        $this->post('/print/'.$job->id.'/documents', [
            'locale' => 'ar',
            'files' => array_map(
                fn (string $name) => UploadedFile::fake()->image($name),
                $names,
            ),
        ]);

        return $job->fresh();
    }

    private function openCashJob(string $kioskId, int $unitPrice): PrintJob
    {
        $job = $this->jobWithImages($kioskId, ['page.jpg']);
        $job->update(['unit_price' => $unitPrice]);

        $this->post(route('print.pay.store', $job), [
            'payment_method' => 'monnayeur',
        ])->assertRedirect(route('print.pay', $job));

        return $job->fresh();
    }

    private function coin(string $kioskId, int $pulses, string $eventKey)
    {
        return $this->withHeader('X-Kiosk-Token', 'test-token')
            ->postJson('/kiosk/'.$kioskId.'/coins', [
                'pulses' => $pulses,
                'event_key' => $eventKey,
            ]);
    }

    private function printer(): FakeKioskPrinter
    {
        $printer = app(KioskPrinter::class);
        $this->assertInstanceOf(FakeKioskPrinter::class, $printer);

        return $printer;
    }

    /**
     * @return list<int>
     */
    private function printedIds(FakeKioskPrinter $printer): array
    {
        return array_merge(...array_map(
            fn (array $unit) => $unit['page_ids'],
            $printer->units,
        ));
    }
}
