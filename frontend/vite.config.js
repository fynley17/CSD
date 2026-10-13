
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  server: {
    host: '0.0.0.0',
    allowedHosts: ['5173--main--ws382593-csd--paulj1499.westcode.remote.ac'],
  },
})
