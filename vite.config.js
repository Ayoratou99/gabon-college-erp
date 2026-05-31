import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/js/app.js',
            ],
            refresh: [
                'resources/views/**',
                'Modules/**/resources/views/**',
                'routes/**',
                'Modules/**/routes/**',
            ],
        }),
    ],
    resolve: {
        alias: {
            '~bootstrap': path.resolve(__dirname, 'node_modules/bootstrap'),
            '~admin-lte': path.resolve(__dirname, 'node_modules/admin-lte'),
            '~fontawesome': path.resolve(__dirname, 'node_modules/@fortawesome/fontawesome-free'),
            '~datatables-bs5': path.resolve(__dirname, 'node_modules/datatables.net-bs5'),
            '~datatables-buttons-bs5': path.resolve(__dirname, 'node_modules/datatables.net-buttons-bs5'),
            '~datatables-responsive-bs5': path.resolve(__dirname, 'node_modules/datatables.net-responsive-bs5'),
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                quietDeps: true,
                silenceDeprecations: ['mixed-decls', 'color-functions', 'global-builtin', 'import'],
            },
        },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        hmr: {
            host: 'localhost',
        },
    },
});
