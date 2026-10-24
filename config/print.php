<?php

return [
    'max_files' => 20,
    'max_kilobytes' => 12288,
    'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp'],
    'mimes' => [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
    ],
    /** Price per printed page in Algerian dinars. */
    'price_per_page' => (float) env('PRINT_PRICE_PER_PAGE', 20),
    /** Ready-made forms are priced separately, per page. */
    'template_price_per_page' => (float) env('PRINT_TEMPLATE_PRICE_PER_PAGE', 50),
    'currency' => 'DA',
    'payment_methods' => ['monnayeur', 'algerie_poste'],
    /**
     * When true, monnayeur inserted amount advances over time for local demos.
     * Set false when a real coin acceptor updates amount_inserted.
     */
    'simulate_monnayeur' => (bool) env('PRINT_SIMULATE_MONNAYEUR', true),
    /** Shared secret for the mini-PC coin agent. Empty rejects every coin. */
    'kiosk_coin_token' => env('KIOSK_COIN_TOKEN', ''),
    /** Debug: Algérie Poste selection completes payment and starts the kiosk print. */
    'emulate_poste_paid' => (bool) env('PRINT_EMULATE_POSTE_PAID', true),
    /** windows sends to the named queue; fake records units for tests. */
    'driver' => env('PRINT_DRIVER', 'windows'),
    'printer_name' => env('PRINT_PRINTER_NAME', 'Canon LBP6030/6040/6018L'),
    'sumatra_path' => env('PRINT_SUMATRA_PATH', base_path('tools/sumatra/SumatraPDF-3.5.2-64.exe')),
    'page_timeout' => (int) env('PRINT_PAGE_TIMEOUT', 90),
];
