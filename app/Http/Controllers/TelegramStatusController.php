<?php

namespace App\Http\Controllers;

use App\Services\Telegram\StatusPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramStatusController extends Controller
{
    public function __invoke(Request $request, string $secret, StatusPublisher $publisher): JsonResponse
    {
        $expected = (string) config('telegram.status_webhook_secret');

        abort_unless($expected !== '' && hash_equals($expected, $secret), 404);

        $text = trim((string) $request->input('message.text', ''));
        $chatId = $request->input('message.chat.id');
        $chatType = (string) $request->input('message.chat.type', '');
        $owner = trim((string) config('telegram.owner_id'));

        if ($chatType !== 'private' || $owner === '' || ! is_numeric($chatId) || ! hash_equals($owner, (string) $chatId)) {
            return response()->json(['ok' => true]);
        }

        if (! str_starts_with($text, '/start')) {
            return response()->json(['ok' => true]);
        }

        $publisher->refresh();

        return response()->json(['ok' => true]);
    }
}
