<?php

namespace App\Http\Controllers;

use App\Models\PrintSetting;
use App\Services\Telegram\OrderNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, OrderNotifier $telegram): JsonResponse
    {
        $expected = (string) config('telegram.webhook_secret');

        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        $text = trim((string) $request->input('message.text', ''));
        $chatId = $request->input('message.chat.id');

        if (! is_numeric($chatId) || ! str_starts_with($text, '/start')) {
            return response()->json(['ok' => true]);
        }

        $settings = PrintSetting::current();
        $incoming = (string) $chatId;

        if ($settings->telegram_chat_id && (string) $settings->telegram_chat_id !== $incoming) {
            return response()->json(['ok' => true]);
        }

        if (! $settings->telegram_chat_id) {
            $settings->update(['telegram_chat_id' => $incoming]);
            $telegram->welcome($incoming);
        }

        return response()->json(['ok' => true]);
    }
}
