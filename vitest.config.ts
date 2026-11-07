import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
            '@academic': fileURLToPath(
                new URL('./packages/academic-mobility/resources/js', import.meta.url),
            ),
            '@agreements': fileURLToPath(
                new URL('./packages/agreements/resources/js', import.meta.url),
            ),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.{ts,tsx}'],
    },
});
