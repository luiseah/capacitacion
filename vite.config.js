import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vite';

// Dev mode for the academic-mobility package: its React sources are read straight
// from the symlinked package folder, so edits show up through HMR without publishing.
const academicMobility = fileURLToPath(
    new URL('./packages/academic-mobility/resources/js', import.meta.url),
);

const agreements = fileURLToPath(
    new URL('./packages/agreements/resources/js', import.meta.url),
);

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        inertia(),
        react(),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
    resolve: {
        alias: { '@academic': academicMobility, '@agreements': agreements },
    },
    server: {
        host: '0.0.0.0',
        origin: 'http://localhost:5173',
        hmr: { host: 'localhost' },
        cors: {
            origin: /^https?:\/\/(localhost|127\.0\.0\.1)(:\d+)?$/,
        },
        watch: {
            ignored: [
                '**/storage/framework/views/**',
                '**/vendor/**',
                '**/.claude/**',
            ],
        },
    },
});
