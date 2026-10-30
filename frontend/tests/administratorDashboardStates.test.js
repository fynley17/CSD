// @vitest-environment jsdom

// Extra tests for the admin dashboard cards, the two states the first test file doesn't reach:
// the wait while PHP is still counting, and a request that fails without PHP sending any message
// each test is laid out Arrange (pick what PHP sends back), Act (open the page), Assert (check the cards)
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdministratorDashboard from '../views/administratorDashboard.vue';
import { getDashboardSummary } from '../services/administratorService';

// swaps the real request for a pretend one, so no PHP or database is needed
vi.mock('../services/administratorService', () => ({
    getDashboardSummary: vi.fn(),
}));

/**
 * Gets the number shown on each card, in the order they show
 * @param page the mounted dashboard
 * @returns the four numbers as text, i.e. ['8', '12', '2', '1']
 */
function cardValues(page) {
    return page.findAll('.card p').map((value) => value.text());
}

// the lecturer table has its own tests, here it's just an empty stand-in so only the cards get checked
const withoutTable = { global: { stubs: { LecturerTable: true } } };

describe('AdministratorDashboard loading and failure states', () => {
    // clears the pretend answer before each test, so one test can't change the next one
    beforeEach(() => {
        vi.mocked(getDashboardSummary).mockReset();
    });

    /**
     * While PHP hasn't answered yet the cards show ... and no error (boundary)
     */
    it('shows ... on every card while the totals are loading', async () => {
        // Arrange
        // a request that never finishes, so the page stays in its waiting state
        vi.mocked(getDashboardSummary).mockReturnValue(new Promise(() => {}));

        // Act
        const page = mount(AdministratorDashboard, withoutTable);
        await flushPromises();

        // Assert
        expect(cardValues(page)).toEqual(['...', '...', '...', '...']);
        expect(page.find('[role="alert"]').exists()).toBe(false);
    });

    /**
     * No reply at all (i.e. PHP is down) still gets a message that says what failed (error)
     */
    it('shows its own message when PHP sends nothing back', async () => {
        // Arrange
        // a network error has no response, so there's no message from PHP to show
        vi.mocked(getDashboardSummary).mockRejectedValue(new Error('Network Error'));

        // Act
        const page = mount(AdministratorDashboard, withoutTable);
        await flushPromises();

        // Assert
        expect(page.get('[role="alert"]').text()).toBe('There is a problem: Unable to load the dashboard totals');
        expect(cardValues(page)).toEqual(['-', '-', '-', '-']);
    });
});
