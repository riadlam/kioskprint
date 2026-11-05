<?php

namespace App\Services\Print;

use App\Events\PrintJobAwaitingPayment;
use App\Jobs\ExpireUnpaidPrint;
use App\Models\PrintCode;
use App\Models\PrintJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Throwable;

class PrintCodeRedeemService
{
    public function __construct(private PrintDocumentStore $documents) {}

    /**
     * @return array<string, mixed>
     */
    public function redeem(string $kioskId, string $pin, string $rateKey): array
    {
        if (! preg_match('/^\d{4}$/', $pin)) {
            throw ValidationException::withMessages([
                'pin' => 'أدخل رمزًا من 4 أرقام.',
            ]);
        }

        if (RateLimiter::tooManyAttempts($rateKey, 10)) {
            throw ValidationException::withMessages([
                'pin' => 'محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.',
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
                'pin' => 'الكشك مشغول الآن. انتظر حتى تنتهي العملية الحالية.',
            ]);
        }

        $code = PrintCode::query()
            ->where('pin', $pin)
            ->where('is_active', true)
            ->whereHas('user', fn ($q) => $q->where('is_active', true)->where('role', 'professor'))
            ->first();

        if (! $code || ! is_file($code->absolutePath())) {
            throw ValidationException::withMessages([
                'pin' => 'الرمز غير صحيح أو غير متاح.',
            ]);
        }

        if ((int) $code->page_count < 1) {
            throw ValidationException::withMessages([
                'pin' => 'هذا المستند غير جاهز للطباعة.',
            ]);
        }

        $holdSeconds = max(5, (int) config('print.coin_hold_seconds', 60));
        $unit = (float) ($code->unit_price ?: config('print.price_per_page', 20));
        $pages = (int) $code->page_count;
        $amount = round($pages * $unit, 2);

        try {
            $job = DB::transaction(function () use ($code, $kioskId, $pages, $unit, $amount) {
                $job = PrintJob::query()->create([
                    'locale' => 'ar',
                    'kiosk_id' => $kioskId,
                    'print_code_id' => $code->id,
                    'title' => $code->title,
                    'status' => 'awaiting_payment',
                    'page_count' => $pages,
                    'unit_price' => $unit,
                    'amount' => $amount,
                    'amount_inserted' => 0,
                    'currency' => $code->currency ?: 'DA',
                    'payment_method' => 'monnayeur',
                    'duplex' => false,
                ]);

                $this->documents->addFromPath(
                    $job,
                    $code->absolutePath(),
                    $code->original_name,
                    'ar',
                    $code->mime_type,
                );

                $pageIds = $job->pages()->pluck('print_pages.id')->map(fn ($id) => (int) $id)->all();

                $job->forceFill([
                    'print_manifest' => ['page_ids' => $pageIds],
                    'page_count' => count($pageIds) > 0 ? count($pageIds) : $pages,
                    'amount' => round(
                        (count($pageIds) > 0 ? count($pageIds) : $pages) * $unit,
                        2
                    ),
                ])->save();

                return $job->fresh(['documents', 'pages']);
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'pin' => 'تعذر تجهيز الطباعة. حاول مرة أخرى.',
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
