<?php

namespace App\Http\Controllers;

use App\Events\PrintJobAwaitingPayment;
use App\Events\PrintJobPaid;
use App\Jobs\PrintKioskJob;
use App\Models\PrintJob;
use App\Models\PrintPage;
use App\Services\Print\CoinCreditService;
use App\Services\Print\PrintDocumentStore;
use App\Services\Print\PrintJobSession;
use App\Services\Print\PrintPricing;
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
    ) {}

    public function begin(Request $request, PrintJob $job): RedirectResponse
    {
        if (! $this->session->owns($job)) {
            return redirect()->route('print.create');
        }

        $data = $request->validate([
            'page_ids' => ['required', 'array', 'min:1'],
            'page_ids.*' => ['integer', 'distinct'],
            'duplex' => ['required', 'boolean'],
        ]);

        $this->documents->reorder($job, $data['page_ids']);

        $updates = ['duplex' => $data['duplex']];

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

        return Inertia::render('Print/Payment', [
            'job' => [
                'id' => $job->id,
                'locale' => $job->locale,
                'duplex' => (bool) $job->duplex,
                'status' => $job->status,
                'payment_method' => $job->payment_method,
                'amount_inserted' => (float) $job->amount_inserted,
            ],
            'quote' => $quote,
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

        $result = DB::transaction(function () use ($job, $data) {
            $locked = PrintJob::query()->whereKey($job->id)->lockForUpdate()->first();

            if (! $locked || $locked->status !== 'reviewing') {
                return 'already';
            }

            if (! $locked->kiosk_id && $this->session->kioskId()) {
                $locked->kiosk_id = $this->session->kioskId();
            }

            $quote = $this->pricing->quote($locked);
            $pageIds = PrintPage::query()
                ->whereIn('print_document_id', $locked->documents()->select('id'))
                ->orderBy('position')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if ($pageIds === [] || count($pageIds) !== $quote['page_count']) {
                return 'mismatch';
            }

            $emulatePoste = $data['payment_method'] === 'algerie_poste'
                && config('print.emulate_poste_paid');

            if ($data['payment_method'] === 'monnayeur' && ! $locked->kiosk_id) {
                return 'no_kiosk';
            }

            if ($emulatePoste && ! $locked->kiosk_id) {
                return 'no_kiosk';
            }

            if ($data['payment_method'] === 'monnayeur') {
                $busy = PrintJob::query()
                    ->where('kiosk_id', $locked->kiosk_id)
                    ->where('status', 'awaiting_payment')
                    ->where('payment_method', 'monnayeur')
                    ->whereKeyNot($locked->id)
                    ->lockForUpdate()
                    ->exists();

                if ($busy) {
                    return 'busy';
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

            return $emulatePoste ? 'print' : 'awaiting';
        });

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

        if ($result === 'print') {
            $job->refresh();
            $this->broadcastSafely(new PrintJobPaid($job));
            PrintKioskJob::dispatch($job->id);
        }

        if ($result === 'awaiting' && $data['payment_method'] === 'monnayeur') {
            $job->refresh();
            $this->broadcastSafely(new PrintJobAwaitingPayment($job));
        }

        return redirect()
            ->route('print.pay', $job)
            ->with('payment_started', $data['payment_method']);
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
