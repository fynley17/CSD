import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [vue()],
    server: {
        host: '0.0.0.0',
        allowedHosts: ['5173--main--ws382593-csd--paulj1499.westcode.remote.ac'],
        // the page runs on port 5173 and PHP runs on 8000, the browser treats those as two different sites
        // and won't send the login cookie between them
        // so vite catches anything starting /api and passes it on to PHP for us, the browser only sees 5173
        proxy: {
            '/api': process.env.VITE_API_PROXY_TARGET || 'http://127.0.0.1:8000',
        },
    },
});
