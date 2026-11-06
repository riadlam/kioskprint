<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
    /** Numeric Telegram user id. Both bots ignore every other chat. */
    'owner_id' => env('TELEGRAM_OWNER_ID', ''),
    'status_bot_token' => env('TELEGRAM_STATUS_BOT_TOKEN', ''),
    'status_webhook_secret' => env('TELEGRAM_STATUS_WEBHOOK_SECRET', ''),
];
