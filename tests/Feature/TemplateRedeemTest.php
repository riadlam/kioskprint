<?php

namespace Tests\Feature;

use App\Models\PrintJob;
use App\Models\PrintTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class TemplateRedeemTest extends TestCase
{
    use RefreshDatabase;

    public function test_tablet_can_redeem_a_ready_form_for_cash_payment(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config([
            'print.desk_token' => 'desk-token',
            'print.template_price_per_page' => 50,
            'print.coin_hold_seconds' => 60,
        ]);

        $relative = 'templates/attestation.pdf';
        $absolute = Storage::disk('public')->path($relative);
        @mkdir(dirname($absolute), 0777, true);
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Text(20, 20, 'Form');
        $pdf->Output('F', $absolute);

        PrintTemplate::query()->create([
            'slug' => 'attestation-test',
            'title_ar' => 'شهادة تجريبية',
            'title_fr' => 'Attestation test',
            'path' => $relative,
            'pages' => 1,
            'position' => 1,
        ]);

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-metbati-01/templates/redeem', [
                'template' => 'attestation-test',
                'locale' => 'ar',
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'page_count' => 1,
                'required' => 50,
                'hold_seconds' => 60,
                'title' => 'شهادة تجريبية',
            ]);

        $job = PrintJob::query()->first();
        $this->assertNotNull($job);
        $this->assertSame('awaiting_payment', $job->status);
        $this->assertSame('monnayeur', $job->payment_method);
        $this->assertSame(50, (int) round((float) $job->amount));
    }

    public function test_busy_kiosk_blocks_template_redeem(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        config(['print.desk_token' => 'desk-token', 'print.template_price_per_page' => 50]);

        $relative = 'templates/busy.pdf';
        $absolute = Storage::disk('public')->path($relative);
        @mkdir(dirname($absolute), 0777, true);
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Text(20, 20, 'Busy');
        $pdf->Output('F', $absolute);

        PrintTemplate::query()->create([
            'slug' => 'busy-form',
            'title_ar' => 'نموذج',
            'title_fr' => 'Form',
            'path' => $relative,
            'pages' => 1,
            'position' => 1,
        ]);

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-busy/templates/redeem', ['template' => 'busy-form'])
            ->assertOk();

        $this->withHeader('X-Kiosk-Desk', 'desk-token')
            ->postJson('/kiosk/kiosk-busy/templates/redeem', ['template' => 'busy-form'])
            ->assertStatus(422);
    }
}
