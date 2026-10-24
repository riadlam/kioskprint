# Kiosk LAN runbook

Change the IP in **two places** (keep them identical):

1. Laravel [`/.env`](.env) → `APP_URL`, `REVERB_HOST`
2. Flutter [`../metbati/lib/config/app_config.dart`](../metbati/lib/config/app_config.dart) → `apiBaseUrl`, `reverbHost`

## Start services

```bash
# Terminal A — HTTP
php artisan serve --host=0.0.0.0 --port=8000

# Terminal B — queue (dummy print progress)
php artisan queue:listen --tries=1

# Terminal C — WebSockets
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Or: `composer run dev` (serve + queue + reverb + vite).

## Flow

1. Run Flutter tablet on the same Wi‑Fi.
2. Scan the QR → phone opens `{APP_URL}/print?kiosk=…`
3. Upload → pay → **Algérie Poste** (debug paid).
4. Tablet switches to printing animation, then success, then back to QR after 5s.
