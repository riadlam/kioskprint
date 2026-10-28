<?php

namespace App\Http\Controllers;

use App\Events\PrintJobAwaitingPayment;
use App\Events\PrintJobFailed;
use App\Events\PrintJobPaid;
use App\Jobs\ExpireUnpaidPrint;
use App\Jobs\PrintKioskJob;
use App\Models\PrintJob;
use App\Models\PrintPage;
use App\Services\Print\CoinCreditService;
use App\Http\Requests\StorePrintFilesRequest;
use App\Services\Print\PrintDocumentStore;
use App\Services\Print\PrintJobSession;
use App\Services\Print\PrintPricing;
use App\Services\SofizPay\SofizPayClient;
use App\Services\Telegram\OrderNotifier;
use Illuminate\Support\Facades\Crypt;
use Throwable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PrintPaymentController extends Controller
{
    public function __construct(
        private PrintJobSession $session,
        private PrintPricing $pricing,
        private PrintDocumentStore $documents,
        private CoinCreditService $credits,
        private OrderNotifier $telegram,
        private SofizPayClient $sofizPay,
    ) {}

    public function begin(Request $request, PrintJob $job): RedirectResponse
    {
        if (! $this->session->owns($job)) {
            return redirect()->route('print.create');
        }

        $maxCopies = max(1, (int) config('print.max_copies', 10));
        $data = $request->validate([
            'page_ids' => ['required', 'array', 'min:1'],
            'page_ids.*' => ['integer', 'distinct'],
            'copies' => ['sometimes', 'array'],
            'copies.*' => ['integer', 'min:1', 'max:'.$maxCopies],
            'duplex' => ['required', 'boolean'],
        ]);

        $copies = array_key_exists('copies', $data) ? array_map('intval', $data['copies']) : [];

        if ($copies !== [] && count($copies) !== count($data['page_ids'])) {
            return back()->withErrors([
                'copies' => 'Each page needs its own copy count.',
            ]);
        }

        $this->documents->reorder($job, $data['page_ids'], $copies);

        $updates = ['duplex' => $data['duplex']];

        if (! $job->kiosk_id && $this->session->kioskId()) {
            $updates['kiosk_id'] = $this->session->kioskId();
        }

        $job->update($updates);

        return redirect()->route('print.pay', $job);
    }

    public function hold(StorePrintFilesRequest $request, PrintJob $job): RedirectResponse
    {
        if (! $this->session->owns($job) || $job->status !== 'reviewing') {
            return redirect()->route('print.create');
        }

        if ($job->documents()->exists()) {
            return redirect()->route('print.pay', $job);
        }

        $data = $request->validate([
            'duplex' => ['required', 'boolean'],
            'selection' => ['required', 'array', 'min:1', 'max:500'],
            'selection.*' => ['required', 'regex:/^\d+:\d+(?::\d{1,2})?$/'],
        ]);

        $maxCopies = max(1, (int) config('print.max_copies', 10));
        $selection = [];

        foreach ($data['selection'] as $item) {
            $parts = array_map('intval', explode(':', $item));
            $copies = $parts[2] ?? 1;

            if ($copies < 1 || $copies > $maxCopies) {
                return back()->withErrors([
                    'selection' => 'Choose between 1 and '.$maxCopies.' copies.',
                ]);
            }

            $selection[] = [$parts[0], $parts[1], $copies];
        }

        $this->documents->hold(
            $job,
            array_values($request->file('files', [])),
            $selection,
            $request->locale(),
        );

        $updates = ['duplex' => $data['duplex'], 'locale' => $request->locale()];

        if (! $job->kiosk_id && $this->session->kioskId()) {
            $updates['kiosk_id'] = $this->session->kioskId();
        }

        $job->update($updates);

        return redirect()->route('print.pay', $job);
    }

    public function show(PrintJob $job): Response|RedirectResponse
    {
        if (! $this->session->owns($job)) {
            return redirect()->route('print.create');
        }

        $quote = $this->pricing->quote($job);

        if ($quote['page_count'] < 1) {
            return redirect()->route('print.review', $job);
        }

        if ($job->status === 'reviewing' && ! $job->payment_method) {
            $this->telegram->pendingChoice($job);
        }

        return Inertia::render('Print/Payment', [
            'job' => [
                'id' => $job->id,
                'locale' => $job->locale,
                'duplex' => (bool) $job->duplex,
                'status' => $job->status,
                'payment_method' => $job->payment_method,
                'amount_inserted' => (float) $job->amount_inserted,
                'printed_pages' => (int) $job->printed_pages,
                'kiosk_id' => $job->kiosk_id,
            ],
            'quote' => $quote,
            'cardMinimum' => $this->sofizPay->minAmount(),
            'methods' => [
                [
                    'id' => 'monnayeur',
                    'icon' => '/images/print/monnayeur.svg',
                ],
                [
                    'id' => 'algerie_poste',
                    'icon' => '/images/print/algerie-poste.png',
                ],
            ],
        ]);
    }

    public function store(Request $request, PrintJob $job): RedirectResponse
    {
        if (! $this->session->owns($job)) {
            return redirect()->route('print.create');
        }

        $quote = $this->pricing->quote($job);

        if ($quote['page_count'] < 1) {
            return redirect()->route('print.review', $job);
        }

        $data = $request->validate([
            'payment_method' => ['required', Rule::in(config('print.payment_methods'))],
        ]);

        $replaced = [];

        $result = DB::transaction(function () use ($job, $data, &$replaced) {
            $locked = PrintJob::query()->whereKey($job->id)->lockForUpdate()->first();

            if (! $locked || ! in_array($locked->status, ['reviewing', 'awaiting_payment'], true)) {
                return 'already';
            }

            if (! $locked->kiosk_id && $this->session->kioskId()) {
                $locked->kiosk_id = $this->session->kioskId();
            }

            $quote = $this->pricing->quote($locked);
            $rows = PrintPage::query()
                ->whereIn('print_document_id', $locked->documents()->select('id'))
                ->orderBy('position')
                ->get(['id', 'copies']);
            $pageIds = $rows->map(fn ($page) => (int) $page->id)->all();
            $sheets = (int) $rows->sum('copies');

            if ($pageIds === [] || $sheets !== $quote['page_count']) {
                return 'mismatch';
            }

            $emulatePoste = $data['payment_method'] === 'algerie_poste'
                && config('print.emulate_poste_paid');
            $satim = $data['payment_method'] === 'algerie_poste' && ! $emulatePoste;

            if ($locked->status === 'awaiting_payment' && ! $satim) {
                return 'already';
            }

            if ($locked->status === 'awaiting_payment' && (float) $locked->amount_inserted > 0) {
                return 'already';
            }

            if ($data['payment_method'] === 'monnayeur' && ! $locked->kiosk_id) {
                return 'no_kiosk';
            }

            if (($emulatePoste || $satim) && ! $locked->kiosk_id) {
                return 'no_kiosk';
            }

            if ($satim && $quote['amount'] < $this->sofizPay->minAmount()) {
                return 'below_minimum';
            }

            if ($satim && ! $this->sofizPay->isConfigured()) {
                return 'sofiz_unavailable';
            }

            if ($data['payment_method'] === 'monnayeur') {
                $others = PrintJob::query()
                    ->where('kiosk_id', $locked->kiosk_id)
                    ->where('status', 'awaiting_payment')
                    ->where('payment_method', 'monnayeur')
                    ->whereKeyNot($locked->id)
                    ->lockForUpdate()
                    ->get();

                $holdingCoins = $others->contains(
                    fn (PrintJob $other) => (float) $other->amount_inserted > 0
                );

                if ($holdingCoins) {
                    return 'busy';
                }

                foreach ($others as $other) {
                    $other->update(['status' => 'failed']);
                    $replaced[] = $other->id;
                }
            }

            $locked->fill([
                'status' => $emulatePoste ? 'paid' : 'awaiting_payment',
                'page_count' => $quote['page_count'],
                'amount' => $quote['amount'],
                'amount_inserted' => $emulatePoste ? $quote['amount'] : 0,
                'currency' => $quote['currency'],
                'payment_method' => $data['payment_method'],
                'paid_at' => $emulatePoste ? now() : null,
                'print_manifest' => ['page_ids' => $pageIds],
            ])->save();

            return $satim ? 'satim' : ($emulatePoste ? 'print' : 'awaiting');
        });

        if ($result === 'below_minimum') {
            return back()->withErrors([
                'payment_method' => 'card_minimum',
            ]);
        }

        if ($result === 'sofiz_unavailable') {
            return back()->withErrors([
                'payment_method' => 'card_unavailable',
            ]);
        }

        if ($result === 'no_kiosk') {
            return back()->withErrors([
                'payment_method' => 'Scan the kiosk QR before paying.',
            ]);
        }

        if ($result === 'busy') {
            return back()->withErrors([
                'payment_method' => 'This kiosk is already taking coins for another print.',
            ]);
        }

        if ($result === 'mismatch') {
            return redirect()->route('print.review', $job);
        }

        foreach ($replaced as $replacedId) {
            $previous = PrintJob::query()->find($replacedId);
            if ($previous) {
                $this->broadcastSafely(new PrintJobFailed($previous));
            }
        }

        if ($result === 'awaiting' || $result === 'print') {
            $job->refresh();
            $this->telegram->pendingMethod($job);

            if ($job->status === 'paid') {
                $this->telegram->complete($job);
            }
        }

        if ($result === 'print') {
            $job->refresh();
            $this->broadcastSafely(new PrintJobPaid($job));
            PrintKioskJob::dispatch($job->id);
        }

        if ($result === 'satim') {
            return $this->openSatim($job);
        }

        if ($result === 'awaiting' && $data['payment_method'] === 'monnayeur') {
            $job->refresh();
            $this->broadcastSafely(new PrintJobAwaitingPayment($job));
            ExpireUnpaidPrint::dispatch($job->id)->delay(
                now()->addSeconds(max(5, (int) config('print.coin_hold_seconds', 20)))
            );
        }

        return redirect()
            ->route('print.pay', $job)
            ->with('payment_started', $data['payment_method']);
    }

    public function satimReturn(Request $request): RedirectResponse
    {
        $job = $this->jobFromReturn($request);

        if (! $job) {
            return redirect()->route('print.create')->with('payment_error', 'not_completed');
        }

        if (! is_string($job->cib_order_number) || $job->cib_order_number === '') {
            return $this->failCard($job, 'not_completed');
        }

        if ($this->callbackSignatureRejected($request)) {
            return $this->failCard($job, 'unconfirmed');
        }

        if (in_array($job->status, ['paid', 'printing', 'printed'], true)) {
            $this->session->resume($job);

            return redirect()->route('print.pay', $job);
        }

        $check = $this->sofizPay->check($job->cib_order_number);
        $data = $check['data'];

        if (! $check['success']) {
            return $this->failCard($job, 'unavailable');
        }

        if ($this->sofizPay->isPaid($data) && (! $this->amountMatches($job, $data) || ! $this->merchantMatches($data))) {
            return $this->failCard($job, 'unconfirmed');
        }

        if (! $this->sofizPay->isPaid($data)) {
            return $this->failCard($job, $this->sofizPay->failureCode($data));
        }

        $paid = $this->sofizPay->paidAmount($data);
        $start = false;

        DB::transaction(function () use ($job, $paid, &$start) {
            $locked = PrintJob::query()->whereKey($job->id)->lockForUpdate()->first();

            if (! $locked || in_array($locked->status, ['paid', 'printing', 'printed'], true)) {
                return;
            }

            $locked->forceFill([
                'status' => 'paid',
                'amount_inserted' => $paid,
                'paid_at' => now(),
            ])->save();
            $start = true;
        });

        $job->refresh();

        if ($start) {
            $this->telegram->complete($job);
            $this->broadcastSafely(new PrintJobPaid($job));
            PrintKioskJob::dispatch($job->id);
        }

        $this->session->resume($job);

        return redirect()->route('print.pay', $job);
    }

    public function status(PrintJob $job): JsonResponse|RedirectResponse
    {
        if (! $this->session->owns($job)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (
            $job->payment_method === 'monnayeur'
            && $job->status === 'awaiting_payment'
            && config('print.simulate_monnayeur')
        ) {
            $this->simulateCoins($job);
            $job->refresh();
        }

        return response()->json($this->credits->view($job));
    }

    private function openSatim(PrintJob $job): RedirectResponse
    {
        $job->refresh();
        $return = route('print.satim.return', [], true)
            .'?eid='.rawurlencode(Crypt::encryptString($job->id));

        $created = $this->sofizPay->create([
            'account' => $this->sofizPay->merchantAccount(),
            'amount' => number_format((float) $job->amount, 2, '.', ''),
            'full_name' => 'Metbati',
            'phone' => '+213000000000',
            'email' => 'pay@metbati.com',
            'return_url' => $return,
            'memo' => 'MB'.substr(str_replace('-', '', $job->id), 0, 18),
            'redirect' => (string) config('services.sofizpay.redirect', 'no'),
            'keep_return_url' => (string) config('services.sofizpay.keep_return_url', 'True'),
        ]);

        $data = $created['data'];
        $paymentUrl = $data['payment_url'] ?? null;
        $orderNumber = isset($data['cib_transaction_id']) ? (string) $data['cib_transaction_id'] : '';

        if (! $created['success'] || $orderNumber === '' || ! is_string($paymentUrl) || filter_var($paymentUrl, FILTER_VALIDATE_URL) === false) {
            $job->update([
                'status' => 'reviewing',
                'payment_method' => null,
            ]);

            return back()->withErrors([
                'payment_method' => 'card_unavailable',
            ]);
        }

        $orderId = null;
        if (isset($data['cib_response']) && is_array($data['cib_response'])) {
            $orderId = $data['cib_response']['orderId'] ?? null;
        }

        $job->update([
            'sofizpay_transaction_id' => isset($data['transaction_id']) ? (string) $data['transaction_id'] : null,
            'cib_order_number' => $orderNumber,
            'cib_order_id' => is_string($orderId) && $orderId !== '' ? $orderId : null,
        ]);

        $this->telegram->pendingMethod($job->fresh());

        return redirect()->away($paymentUrl);
    }

    private function failCard(PrintJob $job, string $code): RedirectResponse
    {
        DB::transaction(function () use ($job) {
            $locked = PrintJob::query()->whereKey($job->id)->lockForUpdate()->first();

            if (! $locked || in_array($locked->status, ['paid', 'printing', 'printed'], true)) {
                return;
            }

            if ((float) $locked->amount_inserted > 0) {
                return;
            }

            $locked->update([
                'status' => 'reviewing',
                'payment_method' => null,
            ]);
        });

        $job->refresh();
        $this->session->resume($job);

        if (in_array($job->status, ['paid', 'printing', 'printed'], true)) {
            return redirect()->route('print.pay', $job);
        }

        return redirect()->route('print.pay', $job)->with('payment_error', $code);
    }

    private function jobFromReturn(Request $request): ?PrintJob
    {
        $eid = (string) $request->input('eid', '');

        if ($eid === '') {
            return null;
        }

        try {
            $id = Crypt::decryptString($eid);
        } catch (Throwable) {
            return null;
        }

        return is_string($id) ? PrintJob::query()->find($id) : null;
    }

    private function callbackSignatureRejected(Request $request): bool
    {
        $message = $request->input('message');
        $signature = $request->input('signature_url_safe', $request->input('signature'));

        if (! is_string($message) || $message === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        return ! $this->sofizPay->signatureIsValid($message, $signature);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function amountMatches(PrintJob $job, array $data): bool
    {
        $paid = $this->sofizPay->paidAmount($data);

        return $paid !== null && abs($paid - round((float) $job->amount, 2)) <= 1.0;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function merchantMatches(array $data): bool
    {
        $merchant = $this->sofizPay->merchantAccount();
        $destination = $this->sofizPay->destination($data);

        return $merchant !== '' && $destination !== null && hash_equals($merchant, $destination);
    }

    private function simulateCoins(PrintJob $job): void
    {
        $required = (int) round((float) $job->amount);
        $started = cache()->remember('coin-sim-start:'.$job->id, 3600, fn () => now()->getTimestamp());
        $elapsed = max(0, now()->getTimestamp() - (int) $started);
        $target = min($required, (int) (floor($elapsed / 1.2) * 5));
        $have = (int) round((float) $job->amount_inserted);
        $delta = $target - $have;

        if ($delta < 1) {
            return;
        }

        $this->credits->addDinars($job, $delta, 'sim:'.$job->id.':'.$target);
    }

    private function broadcastSafely(object $event): void
    {
        try {
            broadcast($event);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
