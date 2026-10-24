import fs from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

function pdfjsAssets() {
    return {
        name: 'pdfjs-assets',
        buildStart() {
            const root = path.resolve('node_modules/pdfjs-dist');
            const dest = path.resolve('public/pdfjs');
            for (const folder of ['cmaps', 'standard_fonts']) {
                fs.cpSync(path.join(root, folder), path.join(dest, folder), { recursive: true });
            }
        },
    };
}

export default defineConfig({
    plugins: [
        pdfjsAssets(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.jsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        cors: true,
        origin: 'http://192.168.1.9:5173',
        hmr: {
            host: '192.168.1.9',
            protocol: 'ws',
            clientPort: 5173,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});


