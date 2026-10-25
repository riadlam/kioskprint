<?php

namespace Tests\Feature;

use App\Events\KioskPcChanged;
use App\Events\KioskPrintUnit as KioskPrintUnitEvent;
use App\Events\PrintJobCompleted;
use App\Events\PrintJobFailed;
use App\Events\PrintJobProgress;
use App\Jobs\PrintKioskJob;
use App\Models\KioskPc;
use App\Models\KioskPrintUnit;
use App\Models\PrintDocument;
use App\Models\PrintJob;
use App\Models\PrintPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class KioskSocketTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'secret-token-1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'print.kiosk_coin_token' => $this->token,
            'broadcasting.connections.reverb.key' => 'app-key',
            'broadcasting.connections.reverb.secret' => 'app-secret',
            'broadcasting.connections.reverb.app_id' => 'app-id',
        ]);
    }

    public function test_socket_auth_rejects_a_bad_token(): void
    {
        $this->postJson('/kiosk/broadcasting/auth', $this->authBody('presence-kiosk-pcs'))
            ->assertStatus(401);
    }

    public function test_socket_auth_signs_the_pc_onto_the_presence_channel(): void
    {
        $response = $this->withHeaders(['X-Kiosk-Token' => $this->token])
            ->postJson('/kiosk/broadcasting/auth', $this->authBody('presence-kiosk-pcs'));

        $response->assertOk();
        $this->assertStringStartsWith('app-key:', (string) $response->json('auth'));
        $member = json_decode((string) $response->json('channel_data'), true);
        $this->assertSame('kiosk-1', $member['user_id']);
    }

    public function test_admin_socket_requires_the_desk_session(): void
    {
        $this->postJson('/admin/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-admin-kiosks',
        ])->assertStatus(401);

        $this->withSession(['admin_ok' => true])
            ->postJson('/admin/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-admin-kiosks',
            ])
            ->assertOk()
            ->assertJsonStructure(['auth']);
    }

    public function test_a_failed_sheet_is_stored_once(): void
    {
        Event::fake([PrintJobFailed::class, KioskPcChanged::class]);
        $unit = $this->pendingUnit();

        $this->withHeaders(['X-Kiosk-Token' => $this->token])
            ->postJson(route('kiosk.units.result', ['kiosk-abc', $unit]), [
                'ok' => false,
                'error' => 'Paper out',
            ])
            ->assertOk()
            ->assertJson(['status' => 'failed']);

        $this->assertSame('failed', $unit->job->fresh()->status);
        $this->assertSame('Paper out', KioskPc::query()->where('kiosk_id', 'kiosk-abc')->value('last_error'));

        $this->withHeaders(['X-Kiosk-Token' => $this->token])
            ->postJson(route('kiosk.units.result', ['kiosk-abc', $unit]), [
                'ok' => false,
                'error' => 'Paper out',
            ])
            ->assertOk()
            ->assertJson(['status' => 'failed']);

        Event::assertDispatchedTimes(PrintJobFailed::class, 1);
        Event::assertDispatchedTimes(KioskPcChanged::class, 1);
    }

    public function test_socket_job_stays_printing_until_the_pc_answers(): void
    {
        config(['print.driver' => 'socket']);
        Event::fake([
            KioskPrintUnitEvent::class,
            PrintJobProgress::class,
            PrintJobCompleted::class,
            KioskPcChanged::class,
        ]);

        $job = $this->paidJob();
        PrintKioskJob::dispatchSync($job->id);

        $job->refresh();
        $this->assertSame('printing', $job->status);
        $unit = KioskPrintUnit::query()->where('print_job_id', $job->id)->first();
        $this->assertNotNull($unit);
        $this->assertSame('pending', $unit->state);
        Event::assertDispatched(KioskPrintUnitEvent::class);
        Event::assertNotDispatched(PrintJobCompleted::class);

        $this->withHeaders(['X-Kiosk-Token' => $this->token])
            ->postJson(route('kiosk.units.result', [$job->kiosk_id, $unit]), [
                'ok' => true,
            ])
            ->assertOk()
            ->assertJson(['status' => 'printed']);

        $this->assertSame('printed', $job->fresh()->status);
        Event::assertDispatched(PrintJobProgress::class);
        Event::assertDispatched(PrintJobCompleted::class);

        $this->withHeaders(['X-Kiosk-Token' => $this->token])
            ->postJson(route('kiosk.units.result', [$job->kiosk_id, $unit]), [
                'ok' => true,
            ])
            ->assertOk()
            ->assertJson(['status' => 'printed']);
    }

    public function test_ready_pushes_a_pending_sheet_once_per_call(): void
    {
        Event::fake([KioskPrintUnitEvent::class]);
        $unit = $this->pendingUnit();

        $this->withHeaders(['X-Kiosk-Token' => $this->token])
            ->postJson(route('kiosk.ready', 'kiosk-abc'))
            ->assertOk();

        Event::assertDispatched(KioskPrintUnitEvent::class, function (KioskPrintUnitEvent $event) use ($unit) {
            return $event->payload['unit_id'] === $unit->id
                && str_contains($event->payload['url'], '/file');
        });
        $pc = KioskPc::query()->where('kiosk_id', 'kiosk-abc')->first();
        $this->assertNotNull($pc);
        $this->assertNotNull($pc->last_seen_at);
        $this->assertNotNull($pc->connected_since);
    }

    /**
     * @return array{socket_id: string, channel_name: string, kiosk_id: string}
     */
    private function authBody(string $channel): array
    {
        return [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
            'kiosk_id' => 'kiosk-1',
        ];
    }

    private function pendingUnit(): KioskPrintUnit
    {
        $job = PrintJob::query()->create([
            'locale' => 'ar',
            'kiosk_id' => 'kiosk-abc',
            'status' => 'printing',
            'page_count' => 1,
            'duplex' => false,
            'currency' => 'DA',
        ]);

        Storage::disk('local')->put('print-spool/'.$job->id.'/unit-1.pdf', "%PDF-1.4\n");

        return KioskPrintUnit::query()->create([
            'print_job_id' => $job->id,
            'kiosk_id' => 'kiosk-abc',
            'position' => 1,
            'page_ids' => [1],
            'path' => 'print-spool/'.$job->id.'/unit-1.pdf',
            'duplex' => false,
            'state' => 'pending',
        ]);
    }

    private function paidJob(): PrintJob
    {
        $job = PrintJob::query()->create([
            'locale' => 'ar',
            'kiosk_id' => 'kiosk-abc',
            'status' => 'paid',
            'page_count' => 1,
            'duplex' => false,
            'currency' => 'DA',
            'print_manifest' => ['page_ids' => []],
        ]);

        $absolute = Storage::disk('local')->path('paid-'.$job->id.'.pdf');
        $directory = dirname($absolute);
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Text(20, 20, 'Metbati');
        $pdf->Output('F', $absolute);

        $document = PrintDocument::query()->create([
            'print_job_id' => $job->id,
            'original_name' => 'page.pdf',
            'mime_type' => 'application/pdf',
            'path' => 'paid-'.$job->id.'.pdf',
            'page_count' => 1,
            'position' => 0,
        ]);
        $page = PrintPage::query()->create([
            'print_document_id' => $document->id,
            'number' => 1,
            'position' => 0,
        ]);
        $job->update(['print_manifest' => ['page_ids' => [$page->id]]]);

        return $job;
    }
}
