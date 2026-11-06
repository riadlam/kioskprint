<?php

namespace Tests\Feature;

use App\Models\PrintDocument;
use App\Models\PrintJob;
use App\Models\PrintPage;
use App\Models\PrintPriceBand;
use App\Services\Print\PrintPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrintPriceTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_pages_use_the_stored_bands(): void
    {
        $this->assertSame(20.0, $this->quoteFor(1)['unit_price']);
        $this->assertSame(20.0, $this->quoteFor(9)['amount'] / 9);
        $this->assertSame(15.0, $this->quoteFor(10)['unit_price']);
        $this->assertSame(15.0, $this->quoteFor(39)['unit_price']);
        $this->assertSame(10.0, $this->quoteFor(40)['unit_price']);
        $this->assertSame(1000.0, $this->quoteFor(100)['amount']);
    }

    public function test_more_than_the_top_band_is_refused(): void
    {
        Storage::fake('local');
        $job = $this->jobWithPages(101);

        $this->withSession(['print_job_ids' => [$job->id]])
            ->get(route('print.pay', $job))
            ->assertRedirect(route('print.review', $job))
            ->assertSessionHasErrors('pages');

        $this->withSession(['print_job_ids' => [$job->id]])
            ->post(route('print.pay.store', $job), [
                'payment_method' => 'algerie_poste',
            ])
            ->assertRedirect(route('print.review', $job))
            ->assertSessionHasErrors('pages');
    }

    public function test_an_upload_past_the_cap_is_rejected(): void
    {
        Storage::fake('local');
        PrintPriceBand::query()->delete();
        PrintPriceBand::query()->create([
            'min_pages' => 1,
            'max_pages' => 2,
            'price_per_page' => 20,
        ]);

        $this->post('/print', [
            'locale' => 'fr',
            'files' => [
                UploadedFile::fake()->image('a.jpg'),
                UploadedFile::fake()->image('b.jpg'),
                UploadedFile::fake()->image('c.jpg'),
            ],
        ])->assertSessionHasErrors('files');

        $this->assertSame(0, PrintJob::query()->count());
    }

    public function test_a_fixed_price_job_ignores_the_bands(): void
    {
        $job = $this->jobWithPages(3, ['unit_price' => 50]);

        $quote = app(PrintPricing::class)->quote($job);

        $this->assertSame(50.0, $quote['unit_price']);
        $this->assertSame(150.0, $quote['amount']);
    }

    public function test_payment_locks_the_band_and_an_admin_edit_changes_the_next_quote(): void
    {
        Storage::fake('local');
        Bus::fake();
        config(['print.emulate_poste_paid' => true, 'queue.default' => 'sync']);

        $job = $this->jobWithPages(10, ['kiosk_id' => 'kiosk-price']);

        $this->withSession(['print_job_ids' => [$job->id]])
            ->post(route('print.pay.store', $job), [
                'payment_method' => 'algerie_poste',
            ])
            ->assertRedirect();

        $job->refresh();
        $this->assertEquals(15, (float) $job->unit_price);
        $this->assertEquals(150, (float) $job->amount);

        config([
            'app.admin_email' => 'admin@metbati.com',
            'app.admin_password' => 'secret',
        ]);
        $this->post('/admin/login', [
            'email' => 'admin@metbati.com',
            'password' => 'secret',
        ]);

        $bands = PrintPriceBand::query()->orderBy('min_pages')->get();
        $this->post('/admin/price-bands', [
            'bands' => $bands->map(fn (PrintPriceBand $band) => [
                'id' => $band->id,
                'min_pages' => $band->min_pages,
                'max_pages' => $band->max_pages,
                'price_per_page' => $band->min_pages === 10 ? 12 : $band->price_per_page,
            ])->all(),
        ])->assertRedirect();

        $job->refresh();
        $this->assertEquals(15, (float) app(PrintPricing::class)->quote($job)['unit_price']);
        $this->assertSame(12.0, app(PrintPricing::class)->quote($this->jobWithPages(10))['unit_price']);
    }

    public function test_review_receives_the_page_cap(): void
    {
        Storage::fake('local');

        $this->post('/print', [
            'locale' => 'ar',
            'files' => [UploadedFile::fake()->image('photo.jpg')],
        ]);

        $job = PrintJob::query()->first();

        $this->get(route('print.review', $job))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('limits.max_pages', 100));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{page_count: int, unit_price: float, amount: float}
     */
    private function quoteFor(int $pages, array $extra = []): array
    {
        return app(PrintPricing::class)->quote($this->jobWithPages($pages, $extra));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function jobWithPages(int $pages, array $extra = []): PrintJob
    {
        $job = PrintJob::query()->create(array_merge([
            'locale' => 'ar',
            'status' => 'reviewing',
            'page_count' => $pages,
            'currency' => 'DA',
        ], $extra));

        $document = PrintDocument::query()->create([
            'print_job_id' => $job->id,
            'original_name' => 'pages.pdf',
            'mime_type' => 'application/pdf',
            'path' => 'print/'.$job->id.'/pages.pdf',
            'page_count' => $pages,
            'position' => 1,
        ]);

        $now = now();
        $rows = [];
        for ($number = 1; $number <= $pages; $number++) {
            $rows[] = [
                'print_document_id' => $document->id,
                'number' => $number,
                'position' => $number,
                'copies' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        PrintPage::query()->insert($rows);

        return $job;
    }
}
