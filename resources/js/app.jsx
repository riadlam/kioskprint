import '../css/app.css';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { prepareScanner } from './lib/geniusScan';

const appName = import.meta.env.VITE_APP_NAME || 'Metbati';

if (typeof document !== 'undefined') {
    try {
        const raw = document.getElementById('app')?.dataset?.page;
        const component = raw ? JSON.parse(raw).component : '';

        if (typeof component === 'string' && component.startsWith('Print/')) {
            prepareScanner();
        }
    } catch {
        // The scan button still loads the scanner if this early start fails.
    }
}

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.jsx`, import.meta.glob('./Pages/**/*.jsx')),
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#059669',
    },
});
