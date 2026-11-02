// @vitest-environment jsdom

// Tests for the cohort management page, PHP is swapped for a pretend service with sample cohorts
// laid out Arrange (open the page), Act (use it), Assert (check the screen)
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { getCohorts, setCohortActive } from '../services/cohortService';
import CohortManagement from '../views/cohortManagement.vue';
import { sampleCohorts } from './sampleCohorts';

vi.mock('../services/cohortService', () => ({
    getCohorts: vi.fn(),
    setCohortActive: vi.fn(),
}));

/**
 * Opens the page and waits for the cohorts to load
 */
async function openPage() {
    const page = mount(CohortManagement);
    await flushPromises();

    return page;
}

/**
 * The cohort names in one of the two top level tables, 0 for students and 1 for subjects
 */
function cohortNames(page, tableIndex) {
    const table = page.findAll('.cohortTable')[tableIndex];

    return table.findAll('.cohortNameCell').map((cell) => cell.text());
}

function findButton(page, label) {
    return page.get(`button[aria-label="${label}"]`);
}

describe('CohortManagement', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        getCohorts.mockResolvedValue(structuredClone(sampleCohorts));
        setCohortActive.mockImplementation(async (cohortID, isActive) => ({
            ...structuredClone(sampleCohorts.find((cohort) => cohort.cohortID === cohortID)),
            isActive,
        }));
    });

    /**
     * Both tables show the first page of cohorts with the paging summary (normal)
     */
    it('shows the first four cohorts in both tables', async () => {
        // Arrange and Act
        const page = await openPage();

        // Assert
        const expected = ['DTS 2026', 'BSc Computing 2025', 'Data Science 2026', 'Software Engineering 2024'];
        expect(cohortNames(page, 0)).toEqual(expected);
        expect(cohortNames(page, 1)).toEqual(expected);
        expect(page.findAll('.cohortPagingSummary')[0].text()).toBe('Showing 1-4 of 8 cohorts');
    });

    /**
     * Every row starts closed, so no students or subjects show until one is opened (normal)
     */
    it('loads with every cohort closed', async () => {
        // Arrange and Act
        const page = await openPage();

        // Assert
        expect(page.find('.cohortDetailsRow').exists()).toBe(false);
        const toggles = page.findAll('.cohortToggle');
        expect(toggles).toHaveLength(8);
        expect(toggles.every((toggle) => toggle.attributes('aria-expanded') === 'false')).toBe(true);
    });

    /**
     * The next page button moves on to the rest of the cohorts (normal)
     */
    it('changes page in one table without moving the other', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await page.findAll('button[aria-label="Next page"]')[0].trigger('click');

        // Assert
        expect(cohortNames(page, 0)[0]).toBe('DTS 2025');
        expect(cohortNames(page, 1)[0]).toBe('DTS 2026');
    });

    /**
     * Opening a row shows its details and tells screen readers it's open (normal)
     */
    it('opens and closes a cohort', async () => {
        // Arrange
        const page = await openPage();
        const toggle = page.findAll('button[aria-label="Show details for BSc Computing 2025"]')[0];
        expect(page.text()).not.toContain('Students in BSc Computing 2025');

        // Act and Assert
        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('true');
        expect(page.text()).toContain('Students in BSc Computing 2025 (31)');

        await toggle.trigger('click');
        expect(toggle.attributes('aria-expanded')).toBe('false');
        expect(page.text()).not.toContain('Students in BSc Computing 2025');
    });

    /**
     * View all swaps the three student preview for everyone in the cohort (normal)
     */
    it('previews three students until View all is pressed', async () => {
        // Arrange
        const page = await openPage();
        await page.findAll('button[aria-label="Show details for DTS 2026"]')[0].trigger('click');
        const studentRows = () => page.get('.cohortDetailsTable').findAll('tbody > tr');
        expect(studentRows()).toHaveLength(3);

        // Act
        await findButton(page, 'View all students in DTS 2026').trigger('click');

        // Assert
        expect(studentRows()).toHaveLength(24);
    });

    /**
     * The search only keeps matching cohorts, and says so when nothing matches (normal and edge)
     */
    it('searches both tables by cohort name', async () => {
        // Arrange
        const page = await openPage();

        // Act and Assert
        await page.get('input[type="search"]').setValue('  science ');
        expect(cohortNames(page, 0)).toEqual(['Data Science 2026', 'Data Science 2025']);
        expect(cohortNames(page, 1)).toEqual(['Data Science 2026', 'Data Science 2025']);

        await page.get('input[type="search"]').setValue('no such cohort');
        expect(page.text()).toContain('No cohorts match your search');
        expect(page.findAll('.cohortPagingSummary')[0].text()).toBe('Showing 0 cohorts');
    });

    /**
     * The status filter leaves only the disabled cohorts (normal)
     */
    it('filters by status', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await page.findAll('select')[0].setValue('disabled');

        // Assert
        expect(cohortNames(page, 0)).toEqual(['Software Engineering 2024', 'DTS 2024']);
    });

    /**
     * Sorting orders the list, and clearing it goes back to the original order (normal)
     */
    it('sorts by name and returns to the original order', async () => {
        // Arrange
        const page = await openPage();
        const select = page.findAll('select')[1];

        // Act and Assert
        await select.setValue('nameAsc');
        expect(cohortNames(page, 0)[0]).toBe('BSc Computing 2024');

        await select.setValue('');
        expect(cohortNames(page, 0)[0]).toBe('DTS 2026');
    });

    /**
     * Going back to the start stops a shorter list leaving an empty page on screen (edge)
     */
    it('goes back to the first page when the list changes', async () => {
        // Arrange
        const page = await openPage();
        await page.findAll('button[aria-label="Next page"]')[0].trigger('click');

        // Act
        await page.get('input[type="search"]').setValue('DTS');

        // Assert
        expect(page.findAll('.cohortPagingSummary')[0].text()).toBe('Showing 1-3 of 3 cohorts');
    });

    /**
     * Only an empty cohort can be deleted (edge)
     */
    it('only lets empty cohorts be deleted', async () => {
        // Arrange and Act
        const page = await openPage();

        // Assert
        expect(findButton(page, 'Delete DTS 2026').attributes('disabled')).toBeDefined();
        expect(findButton(page, 'Delete Data Science 2026').attributes('disabled')).toBeUndefined();
    });

    /**
     * Disabling a cohort updates both tables and offers Enable instead (normal)
     */
    it('disables a cohort and swaps the button to Enable', async () => {
        // Arrange
        const page = await openPage();
        const firstPills = () => page.findAll('.cohortTable').map((table) => table.get('.statusPill').text());

        // Act
        await findButton(page, 'Disable DTS 2026').trigger('click');
        await flushPromises();

        // Assert
        expect(findButton(page, 'Enable DTS 2026').text()).toBe('Enable');
        expect(findButton(page, 'Enable DTS 2026').classes()).toContain('tableEnableButton');
        expect(firstPills()).toEqual(['Disabled', 'Disabled']);
    });

    /**
     * Enabling a disabled cohort puts it back to active (normal)
     */
    it('enables a cohort again', async () => {
        // Arrange
        const page = await openPage();
        await findButton(page, 'Disable DTS 2026').trigger('click');
        await flushPromises();

        // Act
        await findButton(page, 'Enable DTS 2026').trigger('click');
        await flushPromises();

        // Assert
        expect(findButton(page, 'Disable DTS 2026').classes()).toContain('tableDisableButton');
    });

    /**
     * Changing a status doesn't send the user back to the first page (boundary)
     */
    it('stays on the same page after disabling a cohort', async () => {
        // Arrange
        const page = await openPage();
        await findButton(page, 'Next page').trigger('click');
        const summary = page.findAll('.cohortPagingSummary')[0].text();
        const cohortName = cohortNames(page, 0)[0];

        // Act
        await findButton(page, `Disable ${cohortName}`).trigger('click');
        await flushPromises();

        // Assert
        expect(page.findAll('.cohortPagingSummary')[0].text()).toBe(summary);
    });

    /**
     * A cohort disabled while filtering by Active leaves the list (edge)
     */
    it('removes a newly disabled cohort from the active filter', async () => {
        // Arrange
        const page = await openPage();
        await page.findAll('select')[0].setValue('active');

        // Act
        await findButton(page, 'Disable DTS 2026').trigger('click');
        await flushPromises();

        // Assert
        expect(cohortNames(page, 0)).not.toContain('DTS 2026');
    });

    /**
     * The new status is sent to PHP with the cohort's ID (normal)
     */
    it('saves the new status', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await findButton(page, 'Disable DTS 2026').trigger('click');
        await flushPromises();

        // Assert
        expect(setCohortActive).toHaveBeenCalledWith('cohort-1', false);
    });

    /**
     * The students PHP sends back replace the old ones, so their new status shows (normal)
     */
    it('shows the students as disabled once the cohort is disabled', async () => {
        // Arrange
        setCohortActive.mockResolvedValue({
            isActive: false,
            students: [
                { studentNumber: 'WS1', name: 'Joe Bloggs', email: 'a@b.c', lastLogin: null, status: 'Disabled' },
            ],
        });
        const page = await openPage();
        await findButton(page, 'Show details for DTS 2026').trigger('click');

        // Act
        await findButton(page, 'Disable DTS 2026').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.cohortDetailsTable tbody .statusPill').text()).toBe('Disabled');
    });

    /**
     * The status stays as it was and the problem shows if saving fails (error)
     */
    it('keeps the old status when saving fails', async () => {
        // Arrange
        setCohortActive.mockRejectedValue({ response: { data: { error: 'Unable to update the cohort' } } });
        const page = await openPage();

        // Act
        await findButton(page, 'Disable DTS 2026').trigger('click');
        await flushPromises();

        // Assert
        expect(findButton(page, 'Disable DTS 2026').text()).toBe('Disable');
        expect(page.get('[role="alert"]').text()).toContain('Unable to update the cohort');
    });

    /**
     * A problem loading the cohorts shows instead of the tables (error)
     */
    it('shows a message when the cohorts cannot load', async () => {
        // Arrange
        getCohorts.mockRejectedValue(new Error('Network Error'));

        // Act
        const page = await openPage();

        // Assert
        expect(page.get('[role="alert"]').text()).toContain('Unable to load the cohorts');
        expect(page.find('.cohortTable').exists()).toBe(false);
    });
});
