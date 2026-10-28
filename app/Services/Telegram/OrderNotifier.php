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
        $messageId = $this->send("Status: pin_code");

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
    }

    public function pendingChoice(PrintJob $job): void
    {
        $quote = $this->pricing->quote($job);

        $this->edit($job, implode("\n", [
            'Status: pending_payment_1',
            'Pages: '.$quote['page_count'],
            'Amount: '.$this->pricing->format($quote['amount']),
        ]));
    }

    public function pendingMethod(PrintJob $job): void
    {
        $quote = $this->pricing->quote($job);

        $this->edit($job, implode("\n", [
            'Status: pending_payment_2',
            'Method: '.$this->methodName($job),
            'Amount: '.$this->pricing->format($quote['amount']),
        ]));
    }

    public function complete(PrintJob $job): void
    {
        $charged = round((float) $job->amount, 2);
        $paid = round((float) $job->amount_inserted, 2);
        $extra = round(max(0, $paid - $charged), 2);

        $this->edit($job, implode("\n", [
            'Status: complete',
            'Method: '.$this->methodName($job),
            'Charged: '.$this->pricing->format($charged),
            'Paid: '.$this->pricing->format($paid),
            'Extra: '.$this->pricing->format($extra),
        ]));
    }

    public function welcome(string $chatId): void
    {
        $this->sendTo($chatId, 'Metbati orders will arrive in this chat.');
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
