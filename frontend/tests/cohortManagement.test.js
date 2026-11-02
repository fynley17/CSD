// @vitest-environment jsdom

// Tests for the cohort management page, using the sample cohorts it currently shows
// laid out Arrange (open the page), Act (use it), Assert (check the screen)
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import CohortManagement from '../views/cohortManagement.vue';

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
    /**
     * Both tables show the first page of cohorts with the paging summary (normal)
     */
    it('shows the first four cohorts in both tables', () => {
        // Arrange and Act
        const page = mount(CohortManagement);

        // Assert
        const expected = ['DTS 2026', 'BSc Computing 2025', 'Data Science 2026', 'Software Engineering 2024'];
        expect(cohortNames(page, 0)).toEqual(expected);
        expect(cohortNames(page, 1)).toEqual(expected);
        expect(page.findAll('.cohortPagingSummary')[0].text()).toBe('Showing 1-4 of 8 cohorts');
    });

    /**
     * Every row starts closed, so no students or subjects show until one is opened (normal)
     */
    it('loads with every cohort closed', () => {
        // Arrange and Act
        const page = mount(CohortManagement);

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
        const page = mount(CohortManagement);

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
        const page = mount(CohortManagement);
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
        const page = mount(CohortManagement);
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
        const page = mount(CohortManagement);

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
        const page = mount(CohortManagement);

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
        const page = mount(CohortManagement);
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
        const page = mount(CohortManagement);
        await page.findAll('button[aria-label="Next page"]')[0].trigger('click');

        // Act
        await page.get('input[type="search"]').setValue('DTS');

        // Assert
        expect(page.findAll('.cohortPagingSummary')[0].text()).toBe('Showing 1-3 of 3 cohorts');
    });

    /**
     * Only an empty cohort can be deleted (edge)
     */
    it('only lets empty cohorts be deleted', () => {
        // Arrange and Act
        const page = mount(CohortManagement);

        // Assert
        expect(findButton(page, 'Delete DTS 2026').attributes('disabled')).toBeDefined();
        expect(findButton(page, 'Delete Data Science 2026').attributes('disabled')).toBeUndefined();
    });
});
