// Settings for the browser tests in frontend/e2e
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
    testDir: './e2e',
    // .e2e.js so Vitest (npm test) doesn't pick these files up
    testMatch: '**/*.e2e.js',
    use: {
        baseURL: 'http://localhost:5173',
    },
    // each test runs twice, once desktop sized and once phone sized
    projects: [
        { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
        { name: 'phone', use: { ...devices['Pixel 7'] } },
    ],
});
