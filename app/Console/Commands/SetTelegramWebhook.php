<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

class SetTelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook';

    protected $description = 'Register the Metbati Telegram webhook';

    public function handle(): int
    {
        $token = trim((string) config('telegram.bot_token'));
        $secret = trim((string) config('telegram.webhook_secret'));
        $appUrl = rtrim((string) config('app.url'), '/');

        if ($token === '' || $secret === '' || $appUrl === '') {
            $this->error('TELEGRAM_BOT_TOKEN, TELEGRAM_WEBHOOK_SECRET, and APP_URL are required.');

            return self::FAILURE;
        }

        $url = $appUrl.'/telegram/webhook/'.$secret;

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post('https://api.telegram.org/bot'.$token.'/setWebhook', [
                    'url' => $url,
                    'allowed_updates' => ['message'],
                ]);
        } catch (Throwable) {
            $this->error('Telegram webhook was not registered.');

            return self::FAILURE;
        }

        if (! $response->ok() || $response->json('ok') !== true) {
            $this->error('Telegram rejected the webhook.');

            return self::FAILURE;
        }

        $this->info('Telegram webhook is registered.');
        $this->registerStatusBot($appUrl);

        return self::SUCCESS;
    }

    private function registerStatusBot(string $appUrl): void
    {
        $token = trim((string) config('telegram.status_bot_token'));
        $secret = trim((string) config('telegram.status_webhook_secret'));

        if ($token === '' || $secret === '') {
            $this->warn('Status bot webhook was skipped. Set TELEGRAM_STATUS_BOT_TOKEN and TELEGRAM_STATUS_WEBHOOK_SECRET.');

            return;
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post('https://api.telegram.org/bot'.$token.'/setWebhook', [
                    'url' => $appUrl.'/telegram/status/'.$secret,
                    'allowed_updates' => ['message'],
                ]);
        } catch (Throwable) {
            $this->error('Status bot webhook was not registered.');

            return;
        }

        if (! $response->ok() || $response->json('ok') !== true) {
            $this->error('Telegram rejected the status bot webhook.');

            return;
        }

        $this->info('Status bot webhook is registered.');
    }
}
