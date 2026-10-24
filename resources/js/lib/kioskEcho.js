import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

export function kioskEcho() {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (!key) {
        return null;
    }

    window.Pusher = Pusher;

    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'https';
    const port = Number(import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80));
    const csrf = decodeURIComponent(
        (document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/) || [])[1] || '',
    );

    return new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/admin/broadcasting/auth',
        auth: {
            headers: {
                'X-XSRF-TOKEN': csrf,
            },
        },
    });
}
