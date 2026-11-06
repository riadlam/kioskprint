<?php

namespace App\Services\Telegram;

use App\Models\PrintSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class StatusPublisher
{
    public function __construct(private StatusBoard $board) {}

    public function refresh(): void
    {
        $token = trim((string) config('telegram.status_bot_token'));
        $chatId = trim((string) config('telegram.owner_id'));

        if ($token === '' || $chatId === '') {
            return;
        }

        $settings = PrintSetting::current();
        $text = $this->board->text();
        $messageId = $settings->status_message_id;

        if ($messageId) {
            $edited = $this->call($token, 'editMessageText', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
                'text' => $text,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if ($edited === 'ok' || $edited === 'same') {
                return;
            }

            if ($edited !== 'missing') {
                return;
            }

            $settings->forceFill(['status_message_id' => null])->save();
        }

        $sent = $this->call($token, 'sendMessage', [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);

        if (is_numeric($sent)) {
            $settings->forceFill(['status_message_id' => (int) $sent])->save();
        }
    }

    private function call(string $token, string $method, array $body): string|int|null
    {
        try {
            $response = Http::timeout(12)
                ->acceptJson()
                ->asJson()
                ->post('https://api.telegram.org/bot'.$token.'/'.$method, $body);
        } catch (Throwable $exception) {
            Log::warning('Status bot call failed', ['method' => $method, 'error' => $exception->getMessage()]);

            return null;
        }

        $description = (string) $response->json('description', '');

        if ($response->ok() && $response->json('ok') === true) {
            $id = $response->json('result.message_id');

            return is_numeric($id) ? (int) $id : 'ok';
        }

        if (str_contains($description, 'message is not modified')) {
            return 'same';
        }

        if (str_contains($description, 'message to edit not found') || str_contains($description, 'message identifier is not specified')) {
            return 'missing';
        }

        Log::warning('Status bot rejected the update', ['method' => $method, 'status' => $response->status()]);

        return null;
    }
}
