<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install Metbati</title>
    <link rel="icon" href="/images/icon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:500,600,700,800" rel="stylesheet">
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #f3f5f4;
            color: #0d0f14;
            font-family: "Plus Jakarta Sans", ui-sans-serif, system-ui, sans-serif;
        }
        main {
            width: min(440px, 100%);
            background: #fff;
            border: 1px solid rgba(13, 15, 20, 0.08);
            border-radius: 20px;
            padding: 32px 28px;
            box-shadow: 0 12px 40px rgba(16, 21, 19, 0.06);
        }
        .mark {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: #059669;
        }
        h1 {
            margin: 12px 0 0;
            font-size: 32px;
            line-height: 1.1;
            letter-spacing: -0.03em;
        }
        p { margin: 12px 0 0; color: #475467; line-height: 1.55; }
        a.install {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 56px;
            margin-top: 28px;
            border-radius: 14px;
            background: #059669;
            color: #fff;
            font-size: 17px;
            font-weight: 700;
            text-decoration: none;
        }
        .meta { margin-top: 14px; font-size: 13px; color: #475467; }
        ol { margin: 22px 0 0; padding-left: 18px; color: #475467; }
        li { margin-top: 8px; }
    </style>
</head>
<body>
    <main>
        <p class="mark">Metbati</p>
        <h1>Install the tablet app</h1>
        @if ($ready)
            <p>Open this page on the Android tablet, then install the kiosk app. The same link always has the latest build.</p>
            <a class="install" href="{{ route('app.download.file') }}?v={{ $build }}">Install Metbati</a>
            <p class="meta">Updated {{ $updatedAt }} · {{ number_format($bytes / 1048576, 1) }} MB</p>
            <ol>
                <li>Tap Install Metbati.</li>
                <li>If Android asks, allow Chrome to install unknown apps.</li>
                <li>Open Metbati from the app list.</li>
            </ol>
        @else
            <p>The install file is not on the server yet.</p>
        @endif
    </main>
</body>
</html>
