// @vitest-environment jsdom

// Tests for the page guard in router.js, the check that runs before every page change
// it makes sure each account type only gets into its own pages, i.e. a lecturer can't open lecturer management
// each test is laid out Arrange (pick the account), Act (try to open the page), Assert (check where they ended up)
import { beforeEach, describe, expect, it, vi } from 'vitest';
import router from '../router/router';
import { getCurrentUser } from '../services/authService';

// swaps the real getCurrentUser() for a pretend one, so no PHP or database is needed
vi.mock('../services/authService', () => ({
    getCurrentUser: vi.fn(),
    logout: vi.fn(),
}));

describe('Router page guard', () => {
    // starts every test from the landing page with no pretend account set, so one test can't change the next
    beforeEach(async () => {
        vi.mocked(getCurrentUser).mockReset();
        await router.push('/');
    });

    /**
     * An admin can open their own lecturer management page (normal)
     */
    it('lets an admin open lecturer management', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'adm01', accountType: 'admin' });

        // Act
        await router.push('/lecturer-management');
        const pageReached = router.currentRoute.value;

        // Assert
        expect(pageReached.name).toBe('lecturerManagement');
    });

    /**
     * An admin can open the lecturer pages too, since an Administrator is also a Lecturer (normal)
     */
    it('lets an admin open student management', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'adm01', accountType: 'admin' });

        // Act
        await router.push('/student-management');
        const pageReached = router.currentRoute.value;

        // Assert
        expect(pageReached.name).toBe('studentManagement');
    });

    /**
     * A lecturer trying to open an admin page gets sent to the landing page (error)
     */
    it('turns a lecturer away from lecturer management', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'lec01', accountType: 'lecturer' });

        // Act
        await router.push('/lecturer-management');
        const pageReached = router.currentRoute.value;

        // Assert
        expect(pageReached.name).toBe('landingPage');
    });

    /**
     * An admin can't get into the pages only students use, i.e. the leaderboard (error)
     */
    it('turns an admin away from the student leaderboard', async () => {
        // Arrange
        vi.mocked(getCurrentUser).mockResolvedValue({ username: 'adm01', accountType: 'admin' });

        // Act
        await router.push('/leaderboard');
        const pageReached = router.currentRoute.value;

        // Assert
        expect(pageReached.name).toBe('login');
    });

    /**
     * Nobody logged in gets sent to login, which remembers the page they wanted (boundary)
     */
    it('sends a visitor to login', async () => {
        // Arrange
        // null is what getCurrentUser() gives back when nobody's logged in
        vi.mocked(getCurrentUser).mockResolvedValue(null);

        // Act
        await router.push('/lecturer-management');
        const pageReached = router.currentRoute.value;

        // Assert
        expect(pageReached.name).toBe('login');
        expect(pageReached.query.redirect).toBe('/lecturer-management');
    });
});
