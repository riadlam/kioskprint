<?php

namespace Database\Seeders;

use App\Models\PrintTemplate;
use App\Services\Print\PageCounter;
use App\Services\Print\UnreadablePrintFile;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PrintTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->rows() as $position => $row) {
            if (PrintTemplate::query()->where('slug', $row['slug'])->exists()) {
                continue;
            }

            $source = $this->source($row['file']);
            if ($source === null) {
                throw new \RuntimeException('Missing template PDF: '.$row['file']);
            }

            $path = 'templates/'.$row['slug'].'.pdf';
            Storage::disk('public')->put($path, (string) file_get_contents($source));
            $absolute = Storage::disk('public')->path($path);

            PrintTemplate::query()->create([
                'slug' => $row['slug'],
                'title_ar' => $row['title_ar'],
                'title_fr' => $row['title_fr'],
                'path' => $path,
                'pages' => $this->pages($absolute, $row['pages']),
                'position' => $position + 1,
            ]);
        }
    }

    private function source(string $file): ?string
    {
        $public = public_path('templates/'.$file);
        if (is_file($public)) {
            return $public;
        }

        $asset = dirname(base_path()).DIRECTORY_SEPARATOR.'metbati'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'images'.DIRECTORY_SEPARATOR.'templates'.DIRECTORY_SEPARATOR.$file;

        return is_file($asset) ? $asset : null;
    }

    private function pages(string $absolute, int $fallback): int
    {
        try {
            $file = new UploadedFile($absolute, basename($absolute), 'application/pdf', \UPLOAD_ERR_OK, true);

            return app(PageCounter::class)->count($file);
        } catch (UnreadablePrintFile) {
            return $fallback;
        }
    }

    /**
     * @return list<array{slug: string, file: string, title_ar: string, title_fr: string, pages: int}>
     */
    private function rows(): array
    {
        return [
            $this->row('recours-rejet-demande-administrative', 'recours-rejet-demande-administrative-Servicedz.pdf', 'نموذج طعن في رفض طلب إداري', 'Formulaire de recours contre le rejet d’une demande administrative', 1),
            $this->row('recours-contre-decision-de-refus', 'recours-contre-decision-de-refus-Servicedz.pdf', 'نموذج طعن في قرار الرفض', 'Formulaire de recours contre une décision de refus', 1),
            $this->row('recours-arrete-demolition-logement', 'recours-arrete-demolition-logement-Servicedz.pdf', 'نموذج طعن في قرار هدم مسكن', 'Formulaire de recours contre un arrêté de démolition de logement', 2),
            $this->row('plainte-pour-vol', 'plainte-pour-vol-Servicedz.pdf', 'نموذج شكوى سرقة', 'Formulaire de plainte pour vol', 2),
            $this->row('plainte-perte-bagages-voyage', 'plainte-perte-bagages-voyage-Servicedz.pdf', 'نموذج شكوى ضياع الأمتعة في السفر', 'Formulaire de plainte pour perte de bagages en voyage', 2),
            $this->row('plainte-occupation-propriete-privee', 'plainte-occupation-propriete-privee-Servicedz.pdf', 'نموذج شكوى احتلال ملكية خاصة', 'Formulaire de plainte pour occupation d’une propriété privée', 2),
            $this->row('plainte-non-paiement-salaire', 'plainte-non-paiement-salaire-Servicedz.pdf', 'نموذج شكوى عدم دفع الأجر', 'Formulaire de plainte pour non-paiement du salaire', 2),
            $this->row('plainte-moniteur-auto-ecole', 'plainte-moniteur-auto-ecole-Servicedz.pdf', 'نموذج شكوى ضد مدرب مدرسة السياقة', 'Formulaire de plainte contre un moniteur d’auto-école', 1),
            $this->row('plainte-degats-travaux-construction', 'plainte-degats-travaux-construction-Servicedz.pdf', 'نموذج شكوى أضرار أشغال البناء', 'Formulaire de plainte pour dégâts de travaux de construction', 2),
            $this->row('modele-reclamation-erreur-acte-deces', 'modele-reclamation-erreur-acte-deces-Servicedz.pdf', 'نموذج طلب تصحيح شهادة الوفاة', 'Formulaire de demande de rectification d’un acte de décès', 1),
            $this->row('modele-plainte-police', 'modele-plainte-police-Servicedz.pdf', 'نموذج شكوى لدى الشرطة', 'Formulaire de plainte à la police', 1),
            $this->row('modele-declaration-sur-lhonneur-impots', 'modele-declaration-sur-lhonneur-impots-Servicedz.pdf', 'نموذج تصريح شرفي للضرائب', 'Formulaire de déclaration sur l’honneur pour les impôts', 1),
            $this->row('demande-revision-indemnisation-accident-route', 'demande-revision-indemnisation-accident-route-Servicedz.pdf', 'نموذج طلب مراجعة تعويض حادث المرور', 'Formulaire de demande de révision de l’indemnisation d’un accident de la route', 2),
            $this->row('demande-renouvellement-residence-universitaire', 'demande-renouvellement-residence-universitaire-Servicedz.pdf', 'نموذج طلب تجديد الإقامة الجامعية', 'Formulaire de demande de renouvellement de la résidence universitaire', 1),
            $this->row('demande-renouvellement-permis-restauration', 'demande-renouvellement-permis-restauration-Servicedz.pdf', 'نموذج طلب تجديد رخصة المطعم', 'Formulaire de demande de renouvellement du permis de restauration', 1),
            $this->row('demande-regularisation-situation-professionnelle', 'demande-regularisation-situation-professionnelle-Servicedz.pdf', 'نموذج طلب تسوية الوضعية المهنية', 'Formulaire de demande de régularisation de la situation professionnelle', 1),
            $this->row('demande-rectification-donnees-assurance-auto', 'demande-rectification-donnees-assurance-auto-Servicedz.pdf', 'نموذج طلب تصحيح بيانات تأمين السيارة', 'Formulaire de demande de rectification des données d’assurance auto', 1),
            $this->row('demande-raccordement-reseau-eau-potable', 'demande-raccordement-reseau-eau-potable-Servicedz.pdf', 'نموذج طلب الربط بشبكة الماء الشروب', 'Formulaire de demande de raccordement au réseau d’eau potable', 2),
            $this->row('demande-procuration-retraite', 'demande-procuration-retraite-Servicedz.pdf', 'نموذج طلب وكالة قبض التقاعد', 'Formulaire de demande de procuration pour percevoir la retraite', 1),
            $this->row('demande-poste-vacant', 'demande-poste-vacant-Servicedz.pdf', 'نموذج طلب شغل منصب شاغر', 'Formulaire de demande pour un poste vacant', 1),
            $this->row('demande-inscription-master', 'demande-inscription-master-Servicedz.pdf', 'نموذج طلب التسجيل في الماستر', 'Formulaire de demande d’inscription en master', 1),
            $this->row('demande-inscription-logement-promotionnel-aide', 'demande-inscription-logement-promotionnel-aide-Servicedz.pdf', 'نموذج طلب السكن الترقوي المدعم', 'Formulaire de demande de logement promotionnel aidé', 1),
            $this->row('demande-inscription-centre-formation-professionnelle', 'demande-inscription-centre-formation-professionnelle-Servicedz.pdf', 'نموذج طلب التسجيل في مركز التكوين المهني', 'Formulaire de demande d’inscription au centre de formation professionnelle', 1),
            $this->row('demande-enregistrement-enfant-ne-sous-x', 'demande-enregistrement-enfant-ne-sous-x-Servicedz.pdf', 'نموذج طلب تسجيل طفل مولود مجهول النسب', 'Formulaire de demande d’enregistrement d’un enfant né sous X', 2),
            $this->row('demande-de-remboursement-securite-sociale', 'demande-de-remboursement-securite-sociale-Servicedz.pdf', 'نموذج طلب تعويض الضمان الاجتماعي', 'Formulaire de demande de remboursement de la sécurité sociale', 1),
            $this->row('demande-changement-nom-famille', 'demande-changement-nom-famille.pdf', 'نموذج طلب تغيير اسم العائلة', 'Formulaire de demande de changement de nom de famille', 1),
            $this->row('demande-attestation-non-revenu', 'demande-attestation-non-revenu-Servicedz.pdf', 'نموذج طلب شهادة عدم الدخل', 'Formulaire de demande d’attestation de non-revenu', 1),
            $this->row('demande-attestation-experience-professionnelle', 'demande-attestation-experience-professionnelle-Servicedz.pdf', 'نموذج طلب شهادة الخبرة المهنية', 'Formulaire de demande d’attestation d’expérience professionnelle', 1),
            $this->row('contestation-rapport-expertise-automobile', 'contestation-rapport-expertise-automobile-Servicedz.pdf', 'نموذج طعن في تقرير خبرة السيارات', 'Formulaire de contestation du rapport d’expertise automobile', 2),
            $this->row('certificat-medical', 'certificat-medical-Servicedz.pdf', 'نموذج شهادة طبية', 'Formulaire de certificat médical', 1),
            $this->row('attestation-non-revenu', 'attestation-non-revenu-Servicedz.pdf', 'نموذج شهادة عدم الدخل', 'Formulaire d’attestation de non-revenu', 1),
        ];
    }

    /**
     * @return array{slug: string, file: string, title_ar: string, title_fr: string, pages: int}
     */
    private function row(string $slug, string $file, string $titleAr, string $titleFr, int $pages): array
    {
        return [
            'slug' => $slug,
            'file' => $file,
            'title_ar' => $titleAr,
            'title_fr' => $titleFr,
            'pages' => $pages,
        ];
    }
}
