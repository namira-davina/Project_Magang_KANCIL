import { defineConfig, devices } from '@playwright/test';
import { BASE_URL, E2E_ENV, PORT } from './tests/e2e/env.js';

export default defineConfig({
    testDir: './tests/e2e',
    globalSetup: './tests/e2e/global-setup.js',
    fullyParallel: false,
    workers: 1, // satu database SQLite bersama; skenario berurutan
    forbidOnly: !!process.env.CI,
    retries: process.env.CI ? 1 : 0,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : 'list',
    use: {
        baseURL: BASE_URL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        locale: 'id-ID',
        timezoneId: 'Asia/Jakarta',
    },
    projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
    webServer: {
        command: `php artisan serve --host=127.0.0.1 --port=${PORT} --no-reload`,
        url: `${BASE_URL}/login`,
        env: { ...process.env, ...E2E_ENV },
        reuseExistingServer: false,
        timeout: 60_000,
    },
});
