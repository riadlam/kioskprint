<?php

return [
    'max_files' => 20,
    'max_copies' => 10,
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
    /** Shared secret so only the tablet can publish the 3-digit desk code. */
    'desk_token' => env('KIOSK_DESK_TOKEN', ''),
    /** Fixed code for debugging the phone flow. Empty disables it. */
    'debug_desk_code' => env('PRINT_DEBUG_DESK_CODE', '241'),
    /** Phone order access ends after this many minutes without a page request. */
    'session_minutes' => 10,
    /**
     * Kiosk bound to a plain /print visit. The address people type and scan
     * stays https://metbati.com/print; this id is stored in the session.
     */
    'public_kiosk_id' => env('PRINT_PUBLIC_KIOSK', 'kiosk-metbati-01'),
    /** Debug: Algérie Poste selection completes payment and starts the kiosk print. */
    'emulate_poste_paid' => (bool) env('PRINT_EMULATE_POSTE_PAID', true),
    /** windows prints on this machine; socket pushes each sheet to the mini PC; remote posts over HTTP; fake records units for tests. */
    'driver' => env('PRINT_DRIVER', 'windows'),
    'kiosk_url' => env('KIOSK_PRINT_URL', ''),
    'printer_name' => env('PRINT_PRINTER_NAME', 'Canon LBP6030/6040/6018L'),
    'sumatra_path' => env('PRINT_SUMATRA_PATH', base_path('tools/sumatra/SumatraPDF-3.5.2-64.exe')),
    'page_timeout' => (int) env('PRINT_PAGE_TIMEOUT', 90),
];
