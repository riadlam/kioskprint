<?php

namespace Tests\Feature;

use App\Models\PrintTemplate;
use Database\Seeders\PrintTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_forms_page_and_feed_stay_locked_until_an_operator_signs_in(): void
    {
        $this->get('/kiosk/templates')
            ->assertOk()
            ->assertJson(['templates' => []]);

        $this->get('/admin/templates')->assertRedirect('/admin');
        $this->post('/admin/templates')->assertRedirect('/admin');
    }

    public function test_an_operator_can_add_rename_replace_and_delete_a_form(): void
    {
        Storage::fake('public');

        $this->withSession(['admin_ok' => true])
            ->post('/admin/templates', [
                'title_ar' => 'نموذج جديد',
                'title_fr' => 'Nouveau formulaire',
                'file' => $this->pdf('certificat-medical-Servicedz.pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $template = PrintTemplate::query()->first();
        $this->assertNotNull($template);
        $this->assertSame(1, $template->pages);
        $this->assertSame('nouveau-formulaire', $template->slug);
        Storage::disk('public')->assertExists($template->path);

        $this->getJson('/kiosk/templates')
            ->assertOk()
            ->assertJsonPath('templates.0.title_ar', 'نموذج جديد')
            ->assertJsonPath('templates.0.title_fr', 'Nouveau formulaire')
            ->assertJsonPath('templates.0.pages', 1);

        $this->withSession(['admin_ok' => true])
            ->post('/admin/templates/'.$template->id, [
                'title_ar' => 'نموذج معدّل',
                'title_fr' => 'Formulaire modifié',
                'file' => $this->pdf('plainte-pour-vol-Servicedz.pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $template->refresh();
        $this->assertSame('نموذج معدّل', $template->title_ar);
        $this->assertSame('Formulaire modifié', $template->title_fr);
        $this->assertSame(2, $template->pages);
        Storage::disk('public')->assertExists($template->path);

        $this->withSession(['admin_ok' => true])
            ->post('/admin/templates', [
                'title_ar' => 'نص',
                'title_fr' => 'Notes',
                'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
            ])
            ->assertSessionHasErrors('file');

        $this->withSession(['admin_ok' => true])
            ->delete('/admin/templates/'.$template->id)
            ->assertRedirect();

        $this->assertNull(PrintTemplate::query()->find($template->id));
        Storage::disk('public')->assertMissing($template->path);
        $this->getJson('/kiosk/templates')->assertJson(['templates' => []]);
    }

    public function test_the_seeded_forms_match_the_phone_list(): void
    {
        Storage::fake('public');
        $this->seed(PrintTemplateSeeder::class);

        $this->assertSame(31, PrintTemplate::query()->count());
        $this->getJson('/kiosk/templates')
            ->assertOk()
            ->assertJsonPath('templates.0.id', 'recours-rejet-demande-administrative')
            ->assertJsonPath('templates.0.title_ar', 'نموذج طعن في رفض طلب إداري')
            ->assertJsonCount(31, 'templates');
    }

    private function pdf(string $name): UploadedFile
    {
        return new UploadedFile(
            public_path('templates/'.$name),
            $name,
            'application/pdf',
            \UPLOAD_ERR_OK,
            true,
        );
    }
}
