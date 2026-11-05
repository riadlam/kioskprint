<?php

namespace App\Services\Print;

use App\Events\PrintJobAwaitingPayment;
use App\Jobs\ExpireUnpaidPrint;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class TemplateRedeemService
{
    public function __construct(
        private PrintDocumentStore $documents,
        private PrintTemplateCatalog $catalog,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function redeem(string $kioskId, string $templateId, string $locale, string $rateKey): array
    {
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            throw ValidationException::withMessages([
                'template' => 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.',
            ]);
        }

        RateLimiter::hit($rateKey, 600);

        $busy = PrintJob::query()
            ->where('kiosk_id', $kioskId)
            ->where('status', 'awaiting_payment')
            ->where('payment_method', 'monnayeur')
            ->exists();

        if ($busy) {
            throw ValidationException::withMessages([
                'template' => 'الكشك مشغول الآن. انتظر حتى تنتهي العملية الحالية.',
            ]);
        }

        $template = $this->catalog->find($templateId);
        if (! $template || ! is_file($this->catalog->path($template))) {
            throw ValidationException::withMessages([
                'template' => 'هذا النموذج غير متاح.',
            ]);
        }

        $pages = max(1, (int) ($template['pages'] ?? 1));
        $unit = (float) config('print.template_price_per_page', 50);
        $amount = round($pages * $unit, 2);
        $holdSeconds = max(5, (int) config('print.coin_hold_seconds', 60));
        $title = $locale === 'fr' ? $template['title_fr'] : $template['title_ar'];
        $fileName = $title.'.pdf';

        try {
            $job = DB::transaction(function () use ($kioskId, $pages, $unit, $amount, $locale, $title, $fileName, $template) {
                $job = PrintJob::query()->create([
                    'locale' => $locale,
                    'kiosk_id' => $kioskId,
                    'title' => $title,
                    'status' => 'awaiting_payment',
                    'page_count' => $pages,
                    'unit_price' => $unit,
                    'amount' => $amount,
                    'amount_inserted' => 0,
                    'currency' => 'DA',
                    'payment_method' => 'monnayeur',
                    'duplex' => false,
                ]);

                $this->documents->addFromPath(
                    $job,
                    $this->catalog->path($template),
                    $fileName,
                    $locale,
                    'application/pdf',
                );

                $pageIds = $job->pages()->pluck('print_pages.id')->map(fn ($id) => (int) $id)->all();
                $finalPages = count($pageIds) > 0 ? count($pageIds) : $pages;

                $job->forceFill([
                    'print_manifest' => ['page_ids' => $pageIds],
                    'page_count' => $finalPages,
                    'amount' => round($finalPages * $unit, 2),
                ])->save();

                return $job->fresh(['documents', 'pages']);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'template' => 'تعذر تجهيز الطباعة. حاول مرة أخرى.',
            ]);
        }

        RateLimiter::clear($rateKey);

        try {
            broadcast(new PrintJobAwaitingPayment($job));
        } catch (Throwable $exception) {
            report($exception);
        }

        ExpireUnpaidPrint::dispatch($job->id)->delay(now()->addSeconds($holdSeconds));

        $required = (int) round((float) $job->amount);

        return [
            'ok' => true,
            'job_id' => $job->id,
            'title' => (string) $job->title,
            'page_count' => (int) $job->page_count,
            'required' => $required,
            'amount' => $required,
            'amount_inserted' => 0,
            'remaining' => $required,
            'currency' => $job->currency ?: 'DA',
            'payment_method' => 'monnayeur',
            'status' => $job->status,
            'hold_seconds' => $holdSeconds,
        ];
    }
}
