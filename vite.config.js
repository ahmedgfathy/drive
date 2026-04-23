import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: false,
            includeAssets: [
                'pwa/favicon-64.png',
                'pwa/apple-touch-icon.png',
                'pwa/mask-icon.svg',
            ],
            manifest: {
                id: '/',
                name: 'PMS Drive',
                short_name: 'PMS Drive',
                description: 'Secure internal document workspace for Petroleum Marine Services.',
                theme_color: '#072949',
                background_color: '#f2f7fc',
                display: 'standalone',
                orientation: 'portrait-primary',
                start_url: '/',
                scope: '/',
                icons: [
                    {
                        src: '/pwa/icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                    {
                        src: '/pwa/icon-maskable-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                    {
                        src: '/pwa/apple-touch-icon.png',
                        sizes: '180x180',
                        type: 'image/png',
                    },
                ],
                screenshots: [
                    {
                        src: '/pwa/screenshot-wide.png',
                        sizes: '1280x720',
                        type: 'image/png',
                        form_factor: 'wide',
                        label: 'PMS Drive desktop workspace',
                    },
                    {
                        src: '/pwa/screenshot-mobile.png',
                        sizes: '720x1280',
                        type: 'image/png',
                        label: 'PMS Drive mobile workspace',
                    },
                ],
            },
            workbox: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2,mp4}'],
                navigateFallback: '/index.php',
                runtimeCaching: [
                    {
                        urlPattern: ({ request }) => request.destination === 'document',
                        handler: 'NetworkFirst',
                        options: {
                            cacheName: 'pages',
                        },
                    },
                    {
                        urlPattern: ({ request }) => ['style', 'script', 'worker'].includes(request.destination),
                        handler: 'StaleWhileRevalidate',
                        options: {
                            cacheName: 'assets',
                        },
                    },
                    {
                        urlPattern: ({ request }) => ['image', 'font', 'video'].includes(request.destination),
                        handler: 'CacheFirst',
                        options: {
                            cacheName: 'media',
                            expiration: {
                                maxEntries: 60,
                                maxAgeSeconds: 60 * 60 * 24 * 30,
                            },
                        },
                    },
                ],
            },
        }),
    ],
    server: {
        host: '127.0.0.1',
        port: 5173,
        strictPort: true,
        origin: 'http://127.0.0.1:5173',
        cors: {
            origin: 'http://127.0.0.1:8000',
            credentials: true,
        },
        hmr: {
            host: '127.0.0.1',
            port: 5173,
            protocol: 'ws',
            clientPort: 5173,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
