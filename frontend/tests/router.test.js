// @vitest-environment jsdom

import { beforeEach, describe, expect, it, vi } from 'vitest';
import { getCurrentUser } from '../services/authService';
import router from '../router/router.js';

vi.mock('../services/authService', () => ({
    getCurrentUser: vi.fn(),
}));

describe('router guard for student only pages', () => {
    beforeEach(async () => {
        vi.clearAllMocks();
        // start each test from the public landing page
        getCurrentUser.mockResolvedValue(null);
        await router.push('/');
    });

    it('sends signed-out visitors to the landing page', async () => {
        getCurrentUser.mockResolvedValue(null);
        await router.push('/testing');
        expect(router.currentRoute.value.name).toBe('landingPage');
    });

    it('treats an unreachable backend as signed out', async () => {
        getCurrentUser.mockRejectedValue(new Error('Network Error'));
        await router.push('/leaderboard');
        expect(router.currentRoute.value.name).toBe('landingPage');
    });

    it.each([
        ['admin', 'administratorDashboard'],
        ['lecturer', 'lecturerDashboard'],
    ])('sends a signed-in %s to their dashboard', async (accountType, dashboard) => {
        getCurrentUser.mockResolvedValue({ accountType });
        await router.push('/test-selection');
        expect(router.currentRoute.value.name).toBe(dashboard);
    });

    it('lets students open student only pages', async () => {
        getCurrentUser.mockResolvedValue({ accountType: 'student' });
        await router.push('/testing');
        expect(router.currentRoute.value.name).toBe('testing');
    });
});
