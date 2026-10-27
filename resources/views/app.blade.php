<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $scannerPreload = null;
            if (request()->is('print', 'print/*')) {
                $manifestPath = public_path('build/manifest.json');
                if (is_file($manifestPath)) {
                    $manifest = json_decode((string) file_get_contents($manifestPath), true);
                    if (is_array($manifest)) {
                        foreach ($manifest as $entry) {
                            if (is_array($entry) && isset($entry['file']) && str_contains($entry['file'], 'geniusscan-sdk')) {
                                $scannerPreload = 'build/'.$entry['file'];
                                break;
                            }
                        }
                    }
                }
            }
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <link rel="icon" href="/images/icon.svg" type="image/svg+xml" />
        <title inertia>{{ config('app.name', 'Metbati') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.jsx'])
        @if ($scannerPreload)
            <link rel="modulepreload" href="{{ asset($scannerPreload) }}" crossorigin>
        @endif
        @inertiaHead
    </head>
    <body class="antialiased">
        @inertia
    </body>
</html>
