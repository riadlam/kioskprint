<?php

namespace App\Http\Controllers;

use App\Models\PrintSetting;
use App\Services\Print\PaperTray;
use App\Services\Telegram\OrderNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, OrderNotifier $telegram, PaperTray $tray): JsonResponse
    {
        $expected = (string) config('telegram.webhook_secret');

        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        $text = trim((string) $request->input('message.text', ''));
        $chatId = $request->input('message.chat.id');
        $chatType = (string) $request->input('message.chat.type', '');

        if ($chatType !== 'private' || ! is_numeric($chatId) || $text === '') {
            return response()->json(['ok' => true]);
        }

        $settings = PrintSetting::current();
        $incoming = (string) $chatId;
        $command = strtolower((string) strtok($text, " \t"));
        $command = explode('@', $command)[0];

        if (! $this->isOwner($incoming, $settings, $command)) {
            return response()->json(['ok' => true]);
        }

        if ($command === '/start') {
            if ($settings->telegram_chat_id && (string) $settings->telegram_chat_id !== $incoming) {
                return response()->json(['ok' => true]);
            }

            if (! $settings->telegram_chat_id) {
                $settings->update(['telegram_chat_id' => $incoming]);
                $telegram->welcome($incoming);
            }

            return response()->json(['ok' => true]);
        }

        if (! $settings->telegram_chat_id) {
            $settings->update(['telegram_chat_id' => $incoming]);
        }

        if ($command === '/left') {
            $telegram->paperLeft($tray->report());

            return response()->json(['ok' => true]);
        }

        if ($command === '/add') {
            if (! preg_match('/^\/add(?:@\S+)?\s+(\d+)$/i', $text, $matches)) {
                $telegram->paperHint();

                return response()->json(['ok' => true]);
            }

            $sheets = (int) $matches[1];

            if ($sheets < 1 || $sheets > PaperTray::MAX_ADD) {
                $telegram->paperHint();

                return response()->json(['ok' => true]);
            }

            $telegram->paperAdded($sheets, $tray->add($sheets));

            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => true]);
    }

    private function isOwner(string $incoming, PrintSetting $settings, string $command): bool
    {
        $owner = trim((string) config('telegram.owner_id'));

        if ($owner !== '') {
            return hash_equals($owner, $incoming);
        }

        $saved = (string) $settings->telegram_chat_id;

        if ($saved === '') {
            return $command === '/start';
        }

        return hash_equals($saved, $incoming);
    }
}
