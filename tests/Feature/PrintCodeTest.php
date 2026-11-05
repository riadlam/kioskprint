<?php

namespace Tests\Feature;

use App\Models\PrintCode;
use App\Models\PrintJob;
use App\Models\User;
use App\Services\Print\PrintCodeStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class PrintCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.admin_email' => 'admin@metbati.com',
            'app.admin_password' => 'secret',
            'print.desk_token' => 'desk-token',
            'print.price_per_page' => 20,
            'print.coin_hold_seconds' => 60,
        ]);
    }

    public function test_professor_can_manage_own_codes_but_not_admin_coins(): void
    {
        Storage::fake('local');

        $professor = User::factory()->professor()->create([
            'email' => 'prof@metbati.com',
            'password' => 'password123',
        ]);
        $other = User::factory()->professor()->create();

        $this->post('/admin/login', [
            'email' => 'prof@metbati.com',
            'password' => 'password123',
        ])->assertRedirect(route('admin.print-codes.index'));

        $this->get('/admin/coins')->assertForbidden();
        $this->get('/admin/professors')->assertForbidden();
        $this->get('/admin/status')->assertForbidden();

        $this->post('/admin/print-codes', [
            'title' => 'محاضرة 1',
            'file' => UploadedFile::fake()->image('lecture.jpg', 80, 80),
        ])->assertRedirect();

        $code = PrintCode::query()->first();
        $this->assertNotNull($code);
        $this->assertSame($professor->id, $code->user_id);
        $this->assertSame(1, (int) $code->page_count);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string) $code->pin);

        $foreign = PrintCode::query()->create([
            'user_id' => $other->id,
            'title' => 'أخرى',
            'pin' => '9999',
            'original_name' => 'x.jpg',
            'path' => 'print-codes/x.jpg',
            'mime_type' => 'image/jpeg',
            'page_count' => 1,
            'unit_price' => 20,
            'currency' => 'DA',
            'is_active' => true,
            'print_count' => 0,
        ]);

        $this->get('/admin/print-codes/'.$foreign->id)->assertForbidden();
    }

    public function test_admin_can_create_professors(): void
    {
        $this->post('/admin/login', [
            'email' => 'admin@metbati.com',
            'password' => 'secret',
        ])->assertRedirect(route('admin'));

        $this->post('/admin/professors', [
            'name' => 'أستاذ أحمد',
            'email' => 'ahmed@uni.dz',
            'password' => 'password123',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'email' => 'ahmed@uni.dz',
            'role' => User::ROLE_PROFESSOR,
            'is_active' => 1,
        ]);
    }

    public function test_redeem_creates_awaiting_payment_job_with_correct_amount(): void
    {
        Storage::fake('local');
        $code = $this->seedActiveCode(pages: 2, pin: '1234');

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-metbati-01/print-codes/redeem', ['pin' => '1234'])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'page_count' => 2,
                'required' => 40,
                'amount' => 40,
                'hold_seconds' => 60,
                'title' => 'محاضرة الجبر',
            ]);

        $job = PrintJob::query()->first();
        $this->assertNotNull($job);
        $this->assertSame('awaiting_payment', $job->status);
        $this->assertSame('monnayeur', $job->payment_method);
        $this->assertSame($code->id, $job->print_code_id);
        $this->assertSame(40, (int) round((float) $job->amount));
        $this->assertSame(0, (int) $code->fresh()->print_count);
    }

    public function test_print_count_increments_only_when_job_is_printed(): void
    {
        Storage::fake('local');
        $code = $this->seedActiveCode(pages: 1, pin: '4321');

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-a/print-codes/redeem', ['pin' => '4321'])
            ->assertOk();

        $job = PrintJob::query()->first();
        $this->assertSame(0, (int) $code->fresh()->print_count);

        $job->forceFill(['status' => 'printed'])->save();
        app(PrintCodeStats::class)->markPrinted($job->fresh());
        $this->assertSame(1, (int) $code->fresh()->print_count);

        app(PrintCodeStats::class)->markPrinted($job->fresh());
        $this->assertSame(1, (int) $code->fresh()->print_count);

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-b/print-codes/redeem', ['pin' => '4321'])
            ->assertOk();

        $second = PrintJob::query()->whereKeyNot($job->id)->first();
        $this->assertNotNull($second);
        $second->forceFill(['status' => 'printed'])->save();
        app(PrintCodeStats::class)->markPrinted($second->fresh());
        $this->assertSame(2, (int) $code->fresh()->print_count);
    }

    public function test_deleted_code_blocks_new_redeem_but_open_job_keeps_snapshot(): void
    {
        Storage::fake('local');
        $professor = User::factory()->professor()->create([
            'email' => 'prof2@metbati.com',
            'password' => 'password123',
        ]);
        $code = $this->seedActiveCode(pages: 1, pin: '5555', professor: $professor);

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-del/print-codes/redeem', ['pin' => '5555'])
            ->assertOk();

        $job = PrintJob::query()->first();
        $snapshot = $job->documents()->first();
        $this->assertNotNull($snapshot);
        $this->assertTrue(Storage::disk('local')->exists($snapshot->path));

        $this->post('/admin/login', [
            'email' => 'prof2@metbati.com',
            'password' => 'password123',
        ]);
        $this->delete('/admin/print-codes/'.$code->id)->assertRedirect(route('admin.print-codes.index'));

        $this->assertSoftDeleted('print_codes', ['id' => $code->id]);
        $this->assertNull($code->fresh()->pin);

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-del/print-codes/redeem', ['pin' => '5555'])
            ->assertStatus(422);

        $this->assertSame('awaiting_payment', $job->fresh()->status);
        $this->assertTrue(Storage::disk('local')->exists($snapshot->path));
    }

    public function test_busy_kiosk_rejects_second_redeem(): void
    {
        Storage::fake('local');
        $this->seedActiveCode(pages: 1, pin: '1111');
        $this->seedActiveCode(pages: 1, pin: '2222');

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-busy/print-codes/redeem', ['pin' => '1111'])
            ->assertOk();

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-busy/print-codes/redeem', ['pin' => '2222'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'الكشك مشغول الآن. انتظر حتى تنتهي العملية الحالية.']);
    }

    public function test_wrong_pin_and_rate_limit(): void
    {
        Storage::fake('local');
        $this->seedActiveCode(pages: 1, pin: '7777');

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-rl/print-codes/redeem', ['pin' => '0000'])
            ->assertStatus(422);

        $key = 'print-code:kiosk-rl:127.0.0.1';
        RateLimiter::clear($key);
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit($key, 600);
        }

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-rl/print-codes/redeem', ['pin' => '7777'])
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.']);
    }

    public function test_redeem_requires_desk_token(): void
    {
        Storage::fake('local');
        $this->seedActiveCode(pages: 1, pin: '8888');

        $this->postJson('/kiosk/kiosk-x/print-codes/redeem', ['pin' => '8888'])
            ->assertUnauthorized();
    }

    private function seedActiveCode(int $pages, string $pin, ?User $professor = null): PrintCode
    {
        $professor ??= User::factory()->professor()->create();

        $relative = 'print-codes/'.$professor->id.'/fixture-'.$pin.'.pdf';
        $absolute = Storage::disk('local')->path($relative);
        @mkdir(dirname($absolute), 0777, true);

        $pdf = new Fpdi;
        for ($i = 0; $i < $pages; $i++) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->Text(20, 20, 'Metbati page '.($i + 1));
        }
        $pdf->Output('F', $absolute);

        return PrintCode::query()->create([
            'user_id' => $professor->id,
            'title' => 'محاضرة الجبر',
            'pin' => $pin,
            'original_name' => 'lecture.pdf',
            'path' => $relative,
            'mime_type' => 'application/pdf',
            'page_count' => $pages,
            'unit_price' => 20,
            'currency' => 'DA',
            'is_active' => true,
            'print_count' => 0,
        ]);
    }
}
