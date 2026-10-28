<?php

namespace App\Services\Telegram;

use App\Models\PrintJob;
use App\Models\PrintSetting;
use App\Services\Print\PrintPricing;
use Illuminate\Support\Facades\Http;
use Throwable;

class OrderNotifier
{
    public function __construct(private PrintPricing $pricing) {}

    public function pinCode(): void
    {
        $kiosk = session('print_kiosk_id');
        $kiosk = is_string($kiosk) && $kiosk !== '' ? $kiosk : '—';

        $messageId = $this->send($this->card(
            '🔓 Code accepted',
            'pin_code',
            'The desk code was right. A new order is about to start.',
            ['🏪 Kiosk: '.$kiosk],
        ));

        if ($messageId !== null) {
            session(['print_telegram_message_id' => $messageId]);
        }
    }

    public function attach(PrintJob $job): void
    {
        $messageId = session('print_telegram_message_id');

        if (! is_numeric($messageId) || $job->telegram_message_id) {
            return;
        }

        $job->forceFill(['telegram_message_id' => (int) $messageId])->save();
        $this->edit($job, $this->pinBody($job));
    }

    public function filesChanged(PrintJob $job): void
    {
        if ($job->status !== 'reviewing' || $job->payment_method) {
            return;
        }

        $fresh = $job->fresh() ?? $job;
        $this->edit($fresh, $this->pinBody($fresh));
    }

    public function pendingChoice(PrintJob $job): void
    {
        $this->edit($job, $this->card(
            '🛒 Waiting for payment',
            'pending_payment_1',
            'Both ways to pay are on the phone. Waiting for a tap.',
            $this->facts($job),
        ));
    }

    public function pendingMethod(PrintJob $job): void
    {
        $card = $job->payment_method === 'algerie_poste';

        $this->edit($job, $this->card(
            $card ? '💳 Paying by card' : '🪙 Paying with coins',
            'pending_payment_2',
            $card
                ? 'They opened the card page. Waiting for the bank to say yes.'
                : 'Coins are going in. Let’s see if they finish the amount.',
            array_merge(
                ['💳 Method: '.$this->methodName($job)],
                $this->facts($job),
            ),
        ));
    }

    public function complete(PrintJob $job): void
    {
        $charged = round((float) $job->amount, 2);
        $paid = round((float) $job->amount_inserted, 2);
        $extra = round(max(0, $paid - $charged), 2);

        $this->edit($job, $this->card(
            '🎉 Paid',
            'complete',
            'Money is in. The printer is on it.',
            array_merge(
                ['💳 Method: '.$this->methodName($job)],
                $this->facts($job, $charged),
                [
                    '💵 Paid: '.$this->pricing->format($paid),
                    '🎁 Extra: '.$this->pricing->format($extra),
                ],
            ),
        ));
    }

    public function welcome(string $chatId): void
    {
        $this->sendTo($chatId, "👋 Metbati is connected.\nNew orders will update in this chat.");
    }

    private function pinBody(PrintJob $job): string
    {
        return $this->card(
            '📄 Order opened',
            'pin_code',
            'They are choosing what to print.',
            $this->facts($job),
        );
    }

    /**
     * @param  list<string>  $facts
     */
    private function card(string $title, string $status, string $blurb, array $facts): string
    {
        return implode("\n", [
            $title,
            'Status: '.$status,
            '',
            $blurb,
            '',
            ...$facts,
        ]);
    }

    /**
     * @return list<string>
     */
    private function facts(PrintJob $job, ?float $charged = null): array
    {
        $quote = $this->pricing->quote($job);
        $total = $charged ?? $quote['amount'];

        return [
            '🆔 Job: '.$job->id,
            '🏪 Kiosk: '.($job->kiosk_id ?: '—'),
            '📄 Pages: '.$quote['page_count'],
            '💵 Price: '.$quote['unit_formatted'].' / page',
            '💰 Total: '.$this->pricing->format($total),
            '🖨 Sides: '.($job->duplex ? 'both sides' : 'one side'),
            '🌐 Language: '.($job->locale === 'fr' ? 'French' : 'Arabic'),
        ];
    }

    private function methodName(PrintJob $job): string
    {
        return $job->payment_method === 'algerie_poste' ? 'Algérie Poste' : 'Monnayeur';
    }

    private function send(string $text): ?int
    {
        $chatId = $this->chatId();

        if ($chatId === null) {
            return null;
        }

        return $this->sendTo($chatId, $text);
    }

    private function sendTo(string $chatId, string $text): ?int
    {
        $payload = $this->call('sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
        ]);
        $messageId = $payload['result']['message_id'] ?? null;

        return is_numeric($messageId) ? (int) $messageId : null;
    }

    private function edit(PrintJob $job, string $text): void
    {
        $chatId = $this->chatId();

        if ($chatId === null || ! $job->telegram_message_id) {
            return;
        }

        $this->call('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $job->telegram_message_id,
            'text' => $text,
        ]);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function call(string $method, array $body): array
    {
        $token = $this->token();

        if ($token === '') {
            return [];
        }

        try {
            $response = Http::timeout(5)
                ->acceptJson()
                ->asJson()
                ->post('https://api.telegram.org/bot'.$token.'/'.$method, $body);

            $json = $response->json();

            return is_array($json) ? $json : [];
        } catch (Throwable) {
            report(new \RuntimeException('Telegram order update failed.'));

            return [];
        }
    }

    private function token(): string
    {
        $token = config('telegram.bot_token');

        return is_string($token) ? trim($token) : '';
    }

    private function chatId(): ?string
    {
        $id = PrintSetting::query()->orderBy('id')->value('telegram_chat_id');

        if (is_int($id) || is_float($id)) {
            return (string) $id;
        }

        return is_string($id) && $id !== '' ? $id : null;
    }
}
