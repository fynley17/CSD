/* global process */
// Browser tests for the login page, laid out Arrange, Act, Assert. Run with npm run e2e
import { test, expect } from '@playwright/test';

// a dummy admin account, set in the terminal before running so the password never goes in the code
const adminUsername = process.env.E2E_ADMIN_USERNAME;
const adminPassword = process.env.E2E_ADMIN_PASSWORD;

test.describe('Login page', () => {
    /**
     * Wrong details get the error box (error)
     * The username doesn't exist, so no real account gets locked by the test
     */
    test('wrong details are refused', async ({ page }) => {
        // Arrange
        await page.goto('/login');

        // Act
        await page.getByLabel('Username').fill('ws999999');
        await page.getByLabel('Password', { exact: true }).fill('NotTheRightOne1');
        await page.getByRole('button', { name: 'Login' }).click();

        // Assert
        await expect(page.getByRole('alert')).toContainText('Username or password is incorrect');
    });

    /**
     * An admin lands on the admin dashboard (normal)
     */
    test('admin reaches the admin dashboard', async ({ page }) => {
        test.skip(!adminUsername || !adminPassword, 'Set E2E_ADMIN_USERNAME and E2E_ADMIN_PASSWORD first');

        // Arrange
        await page.goto('/login');

        // Act
        await page.getByLabel('Username').fill(adminUsername);
        await page.getByLabel('Password', { exact: true }).fill(adminPassword);
        await page.getByRole('button', { name: 'Login' }).click();

        // Assert
        await expect(page).toHaveURL(/admin-dashboard/);
    });
});
