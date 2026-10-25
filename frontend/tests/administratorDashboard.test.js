// @vitest-environment jsdom

// Tests for the admin dashboard cards, checks the four totals from PHP show on the right cards
// and that a failed request shows a message instead of wrong numbers
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
 * Gets each card's title and number, in the order they show
 * @param page the mounted dashboard
 * @returns pairs like ['Total Subjects', '8']
 */
function cardText(page) {
    return page.findAll('.card').map((card) => [card.get('h2').text(), card.get('p').text()]);
}

describe('AdministratorDashboard', () => {
    // clears the pretend answer before each test, so one test can't change the next one
    beforeEach(() => {
        vi.mocked(getDashboardSummary).mockReset();
    });

    /**
     * The totals from PHP show on their own cards (normal)
     */
    it('shows the totals on the four cards', async () => {
        // Arrange
        vi.mocked(getDashboardSummary).mockResolvedValue({
            totalSubjects: 8,
            totalLecturers: 12,
            archivedSubjects: 2,
            disabledLecturers: 1,
        });

        // Act
        const page = mount(AdministratorDashboard);
        await flushPromises();

        // Assert
        expect(cardText(page)).toEqual([
            ['Total Subjects', '8'],
            ['Total Lecturers', '12'],
            ['Archived Subjects', '2'],
            ['Disabled Lecturers', '1'],
        ]);
        expect(page.find('[role="alert"]').exists()).toBe(false);
    });

    /**
     * A brand new system shows 0 on every card, not a dash or an error (boundary)
     */
    it('shows zeros when there is nothing yet', async () => {
        // Arrange
        vi.mocked(getDashboardSummary).mockResolvedValue({
            totalSubjects: 0,
            totalLecturers: 0,
            archivedSubjects: 0,
            disabledLecturers: 0,
        });

        // Act
        const page = mount(AdministratorDashboard);
        await flushPromises();

        // Assert
        expect(cardText(page).map((card) => card[1])).toEqual(['0', '0', '0', '0']);
    });

    /**
     * If PHP can't send the totals, a message explains it and the cards show a dash (error)
     */
    it('shows a message when the totals fail to load', async () => {
        // Arrange
        vi.mocked(getDashboardSummary).mockRejectedValue({
            response: { data: { error: 'Unable to load the dashboard totals' } },
        });

        // Act
        const page = mount(AdministratorDashboard);
        await flushPromises();

        // Assert
        expect(page.get('[role="alert"]').text()).toBe('There is a problem: Unable to load the dashboard totals');
        expect(cardText(page).map((card) => card[1])).toEqual(['-', '-', '-', '-']);
    });
});
