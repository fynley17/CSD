// @vitest-environment jsdom

// Tests for the cohort management page, PHP is swapped for a pretend service with sample cohorts
// laid out Arrange (open the page), Act (use it), Assert (check the screen)
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    assignSubjects,
    createCohort,
    deleteCohort,
    getCohorts,
    getStudentsOutsideCohort,
    getUnassignedSubjects,
    moveStudents,
    removeSubject,
    setCohortActive,
    updateCohort,
} from '../services/cohortService';
import CohortManagement from '../views/cohortManagement.vue';
import { sampleCohorts } from './sampleCohorts';

vi.mock('../services/cohortService', () => ({
    getCohorts: vi.fn(),
    setCohortActive: vi.fn(),
    getUnassignedSubjects: vi.fn(),
    assignSubjects: vi.fn(),
    removeSubject: vi.fn(),
    deleteCohort: vi.fn(),
    createCohort: vi.fn(),
    updateCohort: vi.fn(),
    getStudentsOutsideCohort: vi.fn(),
    moveStudents: vi.fn(),
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

/**
 * The sort button in a column heading, found by the heading's text
 */
function sortButton(wrapper, heading) {
    return wrapper.findAll('.sortableHeadingButton').find((button) => button.text() === heading);
}

function findButton(page, label) {
    return page.get(`button[aria-label="${label}"]`);
}

describe('CohortManagement', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // jsdom has no showModal(), so a stand-in opens and closes the pop-ups
        HTMLDialogElement.prototype.showModal = vi.fn(function showModal() {
            this.setAttribute('open', '');
        });
        HTMLDialogElement.prototype.close = vi.fn(function close() {
            this.removeAttribute('open');
            this.dispatchEvent(new Event('close'));
        });
        getUnassignedSubjects.mockResolvedValue([
            { subjectID: 'subject-8', subjectName: 'Artificial Intelligence', testCount: 3 },
            { subjectID: 'subject-9', subjectName: 'Project Management', testCount: 1 },
        ]);
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
     * Clicking a heading sorts up, then down, then back to the original order, like DataTables (normal)
     */
    it('sorts a cohort table by a column heading', async () => {
        // Arrange
        const page = await openPage();
        const table = page.findAll('.cohortTable')[0];
        const heading = () => sortButton(table, 'Students').element.closest('th');

        // Act and Assert
        await sortButton(table, 'Students').trigger('click');
        expect(cohortNames(page, 0)[0]).toBe('Data Science 2026');
        expect(heading().getAttribute('aria-sort')).toBe('ascending');
        expect(heading().querySelector('.sortArrowsAsc')).not.toBeNull();

        await sortButton(table, 'Students').trigger('click');
        expect(cohortNames(page, 0)[0]).toBe('BSc Computing 2025');
        expect(heading().getAttribute('aria-sort')).toBe('descending');

        await sortButton(table, 'Students').trigger('click');
        expect(cohortNames(page, 0)[0]).toBe('DTS 2026');
        expect(heading().hasAttribute('aria-sort')).toBe(false);
    });

    /**
     * Each table sorts on its own, sorting one leaves the other alone (normal)
     */
    it('sorts only the table whose heading was clicked', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await sortButton(page.findAll('.cohortTable')[1], 'Subjects').trigger('click');

        // Assert
        expect(cohortNames(page, 1)[0]).toBe('Data Science 2026');
        expect(cohortNames(page, 0)[0]).toBe('DTS 2026');
    });

    /**
     * Picking from Sort by clears a column sort, so the drop down always does something (edge)
     */
    it('clears the column sort when Sort by is used', async () => {
        // Arrange
        const page = await openPage();
        const table = page.findAll('.cohortTable')[0];
        await sortButton(table, 'Students').trigger('click');

        // Act
        await page.findAll('select')[1].setValue('nameAsc');

        // Assert
        expect(cohortNames(page, 0)[0]).toBe('BSc Computing 2024');
        expect(table.find('[aria-sort]').exists()).toBe(false);
    });

    /**
     * The students in an open cohort sort too, before the preview cuts the list down (normal)
     */
    it('sorts the students in a cohort by a column heading', async () => {
        // Arrange
        const page = await openPage();
        await findButton(page, 'Show details for DTS 2026').trigger('click');
        const details = page.get('.cohortDetailsTable');

        // Act
        await sortButton(details, 'Name').trigger('click');

        // Assert
        expect(page.get('.cohortDetailsTable tbody th').text()).toBe('Alex Brown');
    });

    /**
     * The subjects in an open cohort sort by number of tests (normal)
     */
    it('sorts the subjects in a cohort by a column heading', async () => {
        // Arrange
        const page = await openPage();
        await page.findAll('.cohortTable')[1].get('[aria-label="Show details for DTS 2026"]').trigger('click');
        const details = page.findAll('.cohortTable')[1].get('.cohortDetailsTable');

        // Act
        await sortButton(details, 'Tests').trigger('click');
        await sortButton(details, 'Tests').trigger('click');

        // Assert
        const subjects = page.findAll('.cohortTable')[1].findAll('.cohortDetailsTable tbody th');
        expect(subjects.map((cell) => cell.text())).toEqual([
            'Web Applications',
            'Databases',
            'Software Testing',
            'Networking',
        ]);
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

    /**
     * Opens a cohort's subjects and the assign pop-up, DTS 2026 is the first cohort
     */
    async function openAssignDialog() {
        const page = await openPage();
        await page.findAll('.cohortTable')[1].get('[aria-label="Show details for DTS 2026"]').trigger('click');
        await findButton(page, 'Assign subjects to DTS 2026').trigger('click');
        await flushPromises();

        return page;
    }

    async function openRemoveDialog() {
        const page = await openPage();
        await page.findAll('.cohortTable')[1].get('[aria-label="Show details for DTS 2026"]').trigger('click');
        await findButton(page, 'Remove from cohort: Web Applications').trigger('click');
        await flushPromises();

        return page;
    }

    /**
     * The pop-up lists the subjects PHP says are unassigned, with nothing chosen yet (normal)
     */
    it('lists the unassigned subjects in the assign pop-up', async () => {
        // Arrange and Act
        const page = await openAssignDialog();

        // Assert
        expect(page.get('.popupTitle').text()).toBe('Assign Subjects to DTS 2026');
        expect(page.findAll('.cohortDialogOption').map((option) => option.text())).toEqual([
            'Artificial Intelligence',
            'Project Management',
        ]);
        expect(page.get('.cohortDialogButtons .cohortPrimaryButton').attributes('disabled')).toBeDefined();
    });

    /**
     * Searching narrows the list without losing what's already chosen (edge)
     */
    it('keeps a chosen subject when a search hides it', async () => {
        // Arrange
        const page = await openAssignDialog();
        await page.findAll('.cohortDialogOption input')[0].setValue(true);

        // Act
        await page.get('.cohortDialogSearch').setValue('project');

        // Assert
        expect(page.findAll('.cohortDialogOption').map((option) => option.text())).toEqual(['Project Management']);
        expect(page.get('.cohortDialogButtons .cohortPrimaryButton').text()).toBe('Assign 1 Subject');
    });

    /**
     * Assigning saves the chosen subjects, updates the table and closes the pop-up (normal)
     */
    it('assigns the chosen subjects', async () => {
        // Arrange
        assignSubjects.mockResolvedValue({
            subjects: [
                {
                    subjectID: 'subject-8',
                    subjectName: 'Artificial Intelligence',
                    testCount: 3,
                    lecturerNames: null,
                    isActive: true,
                },
            ],
        });
        const page = await openAssignDialog();
        await page.findAll('.cohortDialogOption input')[0].setValue(true);

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(assignSubjects).toHaveBeenCalledWith('cohort-1', ['subject-8']);
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(page.get('.cohortDetailsTable tbody th').text()).toBe('Artificial Intelligence');
    });

    /**
     * The pop-up stays open and shows the problem if assigning fails (error)
     */
    it('shows a problem when assigning fails', async () => {
        // Arrange
        assignSubjects.mockRejectedValue({ response: { data: { error: 'No longer available' } } });
        const page = await openAssignDialog();
        await page.findAll('.cohortDialogOption input')[0].setValue(true);

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('No longer available');
    });

    /**
     * With nothing to assign the pop-up says so (boundary)
     */
    it('says when there are no unassigned subjects', async () => {
        // Arrange
        getUnassignedSubjects.mockResolvedValue([]);

        // Act
        const page = await openAssignDialog();

        // Assert
        expect(page.get('.popupBody').text()).toContain('There are no unassigned subjects to add');
    });

    /**
     * The remove pop-up names the subject, cohort and test count (normal)
     */
    it('asks before removing a subject', async () => {
        // Arrange and Act
        const page = await openRemoveDialog();

        // Assert
        expect(page.get('.popupTitle').text()).toBe('Remove Subject?');
        expect(page.get('.cohortDialogConfirm h3').text()).toBe('Web Applications');
        expect(page.get('.cohortDialogConfirm').text()).toContain(
            "Students in DTS 2026 will no longer see this subject's 6 tests",
        );
        expect(removeSubject).not.toHaveBeenCalled();
    });

    /**
     * Cancel closes the pop-up without removing anything (edge)
     */
    it('leaves the subject in place when cancelled', async () => {
        // Arrange
        const page = await openRemoveDialog();

        // Act
        await page.get('.cohortDialogButtons .cohortSecondaryButton').trigger('click');

        // Assert
        expect(removeSubject).not.toHaveBeenCalled();
        expect(page.find('.popupDialog').exists()).toBe(false);
    });

    /**
     * Confirming removes the subject and updates the table (normal)
     */
    it('removes the subject once confirmed', async () => {
        // Arrange
        removeSubject.mockResolvedValue({ subjects: [] });
        const page = await openRemoveDialog();

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(removeSubject).toHaveBeenCalledWith('cohort-1', 'subject-1');
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(page.findAll('.cohortDetailsEmpty').map((text) => text.text())).toContain(
            'No subjects for this cohort yet',
        );
    });

    /**
     * The date and time from the database are shown the way the page writes dates (normal)
     */
    it('shows the last login as day, month, year and time', async () => {
        // Arrange
        const cohorts = structuredClone(sampleCohorts);
        cohorts[0].students[0].lastLogin = '2026-09-29 09:42:10';
        getCohorts.mockResolvedValue(cohorts);
        const page = await openPage();

        // Act
        await page.findAll('.cohortTable')[0].get('[aria-label="Show details for DTS 2026"]').trigger('click');

        // Assert
        expect(page.get('.cohortDetailsTable tbody tr').text()).toContain('29/09/2026, 09:42');
    });

    /**
     * A student who has never logged in says so (boundary)
     */
    it('shows Never for a student with no last login', async () => {
        // Arrange
        const cohorts = structuredClone(sampleCohorts);
        cohorts[0].students[0].lastLogin = null;
        getCohorts.mockResolvedValue(cohorts);
        const page = await openPage();

        // Act
        await page.findAll('.cohortTable')[0].get('[aria-label="Show details for DTS 2026"]').trigger('click');

        // Assert
        expect(page.get('.cohortDetailsTable tbody tr').text()).toContain('Never');
    });

    /**
     * Each subject shows its status, and says when it has no lecturer (normal)
     */
    it('shows an archived subject with no lecturer', async () => {
        // Arrange
        const cohorts = structuredClone(sampleCohorts);
        cohorts[0].subjects = [
            { subjectID: 'subject-1', subjectName: 'Test', testCount: 0, lecturerNames: null, isActive: false },
        ];
        getCohorts.mockResolvedValue(cohorts);
        const page = await openPage();

        // Act
        await page.findAll('.cohortTable')[1].get('[aria-label="Show details for DTS 2026"]').trigger('click');

        // Assert
        const row = page.get('.cohortDetailsTable tbody tr');
        expect(row.text()).toContain('Not assigned');
        expect(row.get('.statusPill').text()).toBe('Archived');
    });

    /**
     * The button counts what's chosen, and only says Subject for one (boundary)
     */
    it('counts the chosen subjects on the assign button', async () => {
        // Arrange
        const page = await openAssignDialog();
        const options = page.findAll('.cohortDialogOption input');
        const assignButton = page.get('.cohortDialogButtons .cohortPrimaryButton');
        const labelWithNone = assignButton.text();

        // Act
        await options[0].setValue(true);
        const labelWithOne = assignButton.text();
        await options[1].setValue(true);
        const labelWithTwo = assignButton.text();

        // Assert
        expect(labelWithNone).toBe('Assign Subjects');
        expect(labelWithOne).toBe('Assign 1 Subject');
        expect(labelWithTwo).toBe('Assign 2 Subjects');
    });

    /**
     * A search with no match says so, rather than showing an empty table (edge)
     */
    it('says when no subject matches the search', async () => {
        // Arrange
        const page = await openAssignDialog();

        // Act
        await page.get('.cohortDialogSearch').setValue('zzz');

        // Assert
        expect(page.get('.cohortDialogTable tbody').text()).toBe('No subjects match your search');
    });

    /**
     * A problem loading the subjects shows in the pop-up (error)
     */
    it('shows a problem when the unassigned subjects cannot load', async () => {
        // Arrange
        getUnassignedSubjects.mockRejectedValue(new Error('Network Error'));

        // Act
        const page = await openAssignDialog();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Unable to load the subjects');
        expect(page.get('.popupBody').text()).not.toContain('There are no unassigned subjects');
    });

    /**
     * One test isn't written as tests (boundary)
     */
    it('writes a single test correctly in the remove pop-up', async () => {
        // Arrange
        const cohorts = structuredClone(sampleCohorts);
        cohorts[0].subjects[0].testCount = 1;
        getCohorts.mockResolvedValue(cohorts);

        // Act
        const page = await openRemoveDialog();

        // Assert
        expect(page.get('.cohortDialogConfirm').text()).toContain("this subject's 1 test.");
    });

    /**
     * The pop-up stays open and the subject stays listed if removing fails (error)
     */
    it('shows a problem when removing fails', async () => {
        // Arrange
        removeSubject.mockRejectedValue({ response: { data: { error: 'That subject is not in this cohort' } } });
        const page = await openRemoveDialog();

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('That subject is not in this cohort');
        expect(page.get('.cohortDetailsTable tbody th').text()).toBe('Web Applications');
    });

    /**
     * The table buttons use the same Bootstrap outline buttons as every other table (normal)
     */
    it('styles the action buttons like the rest of the site', async () => {
        // Arrange and Act
        const page = await openPage();

        // Assert
        expect(findButton(page, 'Delete DTS 2026').classes()).toContain('btn-outline-danger');
        expect(findButton(page, 'Disable DTS 2026').classes()).toContain('tableDisableButton');
    });

    /**
     * The problem from the last save clears when the next one works (edge)
     */
    it('clears the save problem once a later save works', async () => {
        // Arrange
        setCohortActive.mockRejectedValueOnce(new Error('Network Error'));
        const page = await openPage();
        await findButton(page, 'Disable DTS 2026').trigger('click');
        await flushPromises();
        const problem = page.get('[role="alert"]').text();

        // Act
        await findButton(page, 'Disable DTS 2026').trigger('click');
        await flushPromises();

        // Assert
        expect(problem).toContain('Unable to update DTS 2026');
        expect(page.find('[role="alert"]').exists()).toBe(false);
    });

    /**
     * An empty cohort asks for confirmation before anything is deleted (normal)
     */
    it('asks before deleting an empty cohort', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await findButton(page, 'Delete Data Science 2026').trigger('click');

        // Assert
        expect(page.get('.popupTitle').text()).toBe('Delete Cohort?');
        expect(page.get('.cohortDialogConfirm h3').text()).toBe('Data Science 2026');
        expect(deleteCohort).not.toHaveBeenCalled();
    });

    /**
     * Confirming deletes the cohort and takes it off both tables (normal)
     */
    it('deletes an empty cohort once confirmed', async () => {
        // Arrange
        deleteCohort.mockResolvedValue({});
        const page = await openPage();
        await findButton(page, 'Delete Data Science 2026').trigger('click');

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(deleteCohort).toHaveBeenCalledWith('cohort-3');
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(cohortNames(page, 0)).not.toContain('Data Science 2026');
        expect(cohortNames(page, 1)).not.toContain('Data Science 2026');
    });

    /**
     * Cancel closes the pop-up and the cohort stays (edge)
     */
    it('keeps the cohort when deleting is cancelled', async () => {
        // Arrange
        const page = await openPage();
        await findButton(page, 'Delete Data Science 2026').trigger('click');

        // Act
        await page.get('.cohortDialogButtons .cohortSecondaryButton').trigger('click');

        // Assert
        expect(deleteCohort).not.toHaveBeenCalled();
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(cohortNames(page, 0)).toContain('Data Science 2026');
    });

    /**
     * The pop-up stays open and the cohort stays listed if PHP refuses (error)
     */
    it('shows the problem when PHP refuses the delete', async () => {
        // Arrange
        deleteCohort.mockRejectedValue({ response: { data: { error: 'This cohort still has students' } } });
        const page = await openPage();
        await findButton(page, 'Delete Data Science 2026').trigger('click');

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('This cohort still has students');
        expect(cohortNames(page, 0)).toContain('Data Science 2026');
    });

    /**
     * A cohort with students can't be deleted, the only offer is to disable it (normal)
     */
    it('offers to disable a cohort that has students', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await findButton(page, 'Delete DTS 2026').trigger('click');

        // Assert
        expect(page.get('.popupTitle').text()).toBe('Cannot Delete Cohort');
        expect(page.get('.cohortDialogConfirm').text()).toContain('24 students and 4 subjects');
        expect(page.get('.cohortDialogButtons .cohortPrimaryButton').text()).toBe('Disable Cohort');
    });

    /**
     * Taking the offer disables the cohort rather than deleting it (normal)
     */
    it('disables the cohort when the offer is taken', async () => {
        // Arrange
        const page = await openPage();
        await findButton(page, 'Delete DTS 2026').trigger('click');

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(setCohortActive).toHaveBeenCalledWith('cohort-1', false);
        expect(deleteCohort).not.toHaveBeenCalled();
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(findButton(page, 'Enable DTS 2026').text()).toBe('Enable');
    });

    /**
     * A cohort that's already disabled has nothing to offer, just a way out (edge)
     */
    it('makes no offer when the cohort is already disabled', async () => {
        // Arrange
        const page = await openPage();
        await page.findAll('select')[0].setValue('disabled');

        // Act
        await findButton(page, 'Delete Software Engineering 2024').trigger('click');

        // Assert
        expect(page.get('.cohortDialogConfirm').text()).toContain('already disabled');
        expect(page.find('.cohortDialogButtons .cohortPrimaryButton').exists()).toBe(false);
        expect(page.get('.cohortDialogButtons .cohortSecondaryButton').text()).toBe('Close');
    });

    /**
     * Subjects but no students means removing the subjects first, disabling wouldn't help (edge)
     */
    it('says to remove the subjects when a cohort only has subjects', async () => {
        // Arrange
        const cohorts = structuredClone(sampleCohorts);
        cohorts[0].students = [];
        getCohorts.mockResolvedValue(cohorts);
        const page = await openPage();

        // Act
        await findButton(page, 'Delete DTS 2026').trigger('click');

        // Assert
        expect(page.get('.cohortDialogConfirm').text()).toContain('Remove its subjects from the cohort first');
        expect(page.find('.cohortDialogButtons .cohortPrimaryButton').exists()).toBe(false);
    });

    /**
     * One student and no subjects are written correctly in the offer (boundary)
     */
    it('writes a single student correctly in the delete pop-up', async () => {
        // Arrange
        const cohorts = structuredClone(sampleCohorts);
        cohorts[0].students = cohorts[0].students.slice(0, 1);
        cohorts[0].subjects = [];
        getCohorts.mockResolvedValue(cohorts);
        const page = await openPage();

        // Act
        await findButton(page, 'Delete DTS 2026').trigger('click');

        // Assert
        expect(page.get('.cohortDialogConfirm').text()).toContain('This cohort has 1 student, so');
    });

    /**
     * The pop-up stays open and the cohort stays active if disabling fails (error)
     */
    it('shows a problem when disabling from the delete pop-up fails', async () => {
        // Arrange
        setCohortActive.mockRejectedValue(new Error('Network Error'));
        const page = await openPage();
        await findButton(page, 'Delete DTS 2026').trigger('click');

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Unable to disable DTS 2026');
        expect(findButton(page, 'Disable DTS 2026').text()).toBe('Disable');
    });

    /**
     * The name box in the cohort pop-up and the button that saves it
     */
    function cohortForm(page) {
        return {
            name: page.get('#cohortName'),
            form: page.get('#cohortForm'),
            status: page.get('#cohortStatus'),
            saveButton: page.get('.cohortDialogButtons .cohortPrimaryButton'),
        };
    }

    /**
     * Create Cohort opens an empty pop-up (normal)
     */
    it('opens an empty form to create a cohort', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await page.get('.cohortCreateButton').trigger('click');

        // Assert
        expect(page.get('.popupTitle').text()).toBe('Create Cohort');
        expect(cohortForm(page).name.element.value).toBe('');
        expect(cohortForm(page).saveButton.text()).toBe('Create Cohort');
    });

    /**
     * Saving adds the new cohort to the top of the tables and closes the pop-up (normal)
     */
    it('creates a cohort and shows it straight away', async () => {
        // Arrange
        createCohort.mockResolvedValue({
            cohortID: 'cohort-new',
            cohortName: 'New Cohort',
            createdDate: '2026-11-03',
            isActive: true,
            students: [],
            subjects: [],
        });
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await cohortForm(page).name.setValue('New Cohort');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(createCohort).toHaveBeenCalledWith('New Cohort', true);
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(cohortNames(page, 0)[0]).toBe('New Cohort');
    });

    /**
     * An empty name is stopped before anything is sent (error)
     */
    it('rejects an empty name without calling PHP', async () => {
        // Arrange
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await cohortForm(page).name.setValue('   ');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(createCohort).not.toHaveBeenCalled();
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Enter a cohort name');
        expect(cohortForm(page).name.attributes('aria-invalid')).toBe('true');
    });

    /**
     * PHP's duplicate message shows and the pop-up stays open to fix it (error)
     */
    it('shows the problem when the name is a duplicate', async () => {
        // Arrange
        createCohort.mockRejectedValue({
            response: { data: { error: 'A cohort with this name already exists', field: 'cohortName' } },
        });
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await cohortForm(page).name.setValue('DTS 2026');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('A cohort with this name already exists');
        expect(cohortNames(page, 0)).toHaveLength(4);
    });

    /**
     * Edit opens the pop-up with the current name filled in (normal)
     */
    it('opens the form with the current name to edit a cohort', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await findButton(page, 'Edit DTS 2026').trigger('click');

        // Assert
        expect(page.get('.popupTitle').text()).toBe('Edit Cohort');
        expect(cohortForm(page).name.element.value).toBe('DTS 2026');
        expect(cohortForm(page).saveButton.text()).toBe('Save Changes');
    });

    /**
     * Saving updates the name in both tables and closes the pop-up (normal)
     */
    it('renames a cohort in both tables', async () => {
        // Arrange
        updateCohort.mockResolvedValue({ cohortName: 'DTS 2027', isActive: true });
        const page = await openPage();
        await findButton(page, 'Edit DTS 2026').trigger('click');
        await cohortForm(page).name.setValue('DTS 2027');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(updateCohort).toHaveBeenCalledWith('cohort-1', 'DTS 2027', true);
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(cohortNames(page, 0)).toContain('DTS 2027');
        expect(cohortNames(page, 1)).toContain('DTS 2027');
    });

    /**
     * Cancel leaves the name as it was (edge)
     */
    it('keeps the old name when editing is cancelled', async () => {
        // Arrange
        const page = await openPage();
        await findButton(page, 'Edit DTS 2026').trigger('click');
        await cohortForm(page).name.setValue('Something Else');

        // Act
        await page.get('.cohortDialogButtons .cohortSecondaryButton').trigger('click');

        // Assert
        expect(updateCohort).not.toHaveBeenCalled();
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(cohortNames(page, 0)).toContain('DTS 2026');
    });

    /**
     * Opening Create after Edit doesn't carry the old name over (edge)
     */
    it('starts the create form empty after editing a cohort', async () => {
        // Arrange
        const page = await openPage();
        await findButton(page, 'Edit DTS 2026').trigger('click');
        await page.get('.cohortDialogButtons .cohortSecondaryButton').trigger('click');

        // Act
        await page.get('.cohortCreateButton').trigger('click');

        // Assert
        expect(cohortForm(page).name.element.value).toBe('');
    });

    /**
     * The edit button uses the same Bootstrap outline button as every other table (normal)
     */
    it('styles the edit button like the rest of the site', async () => {
        // Arrange and Act
        const page = await openPage();

        // Assert
        expect(findButton(page, 'Edit DTS 2026').classes()).toContain('btn-outline-primary');
    });

    /**
     * A new cohort is enabled unless the admin chooses otherwise (normal)
     */
    it('starts the create form as enabled', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await page.get('.cohortCreateButton').trigger('click');

        // Assert
        expect(cohortForm(page).status.element.value).toBe('enabled');
        expect(page.find('#cohortStatusNote').exists()).toBe(false);
    });

    /**
     * A cohort can be created as disabled, with a note on what that means (normal)
     */
    it('creates a disabled cohort', async () => {
        // Arrange
        createCohort.mockResolvedValue({
            cohortID: 'cohort-new',
            cohortName: 'New Cohort',
            createdDate: '2026-11-03',
            isActive: false,
            students: [],
            subjects: [],
        });
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await cohortForm(page).name.setValue('New Cohort');
        await cohortForm(page).status.setValue('disabled');
        const note = page.get('#cohortStatusNote').text();

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(note).toContain("can't log in");
        expect(createCohort).toHaveBeenCalledWith('New Cohort', false);
        expect(findButton(page, 'Enable New Cohort').text()).toBe('Enable');
    });

    /**
     * Editing a disabled cohort shows its current status (edge)
     */
    it('shows the current status when editing a disabled cohort', async () => {
        // Arrange
        const page = await openPage();
        await page.findAll('select')[0].setValue('disabled');

        // Act
        await findButton(page, 'Edit Software Engineering 2024').trigger('click');

        // Assert
        expect(cohortForm(page).status.element.value).toBe('disabled');
    });

    /**
     * Changing the status while editing saves it and updates the table (normal)
     */
    it('disables a cohort from the edit form', async () => {
        // Arrange
        updateCohort.mockResolvedValue({ cohortName: 'DTS 2026', isActive: false });
        const page = await openPage();
        await findButton(page, 'Edit DTS 2026').trigger('click');
        await cohortForm(page).status.setValue('disabled');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(updateCohort).toHaveBeenCalledWith('cohort-1', 'DTS 2026', false);
        expect(findButton(page, 'Enable DTS 2026').text()).toBe('Enable');
    });

    /**
     * A failed edit stays open, and the tables keep the old name and status (error)
     */
    it('keeps the old details when saving an edit fails', async () => {
        // Arrange
        updateCohort.mockRejectedValue({ response: { data: { error: 'A cohort with this name already exists' } } });
        const page = await openPage();
        await findButton(page, 'Edit DTS 2026').trigger('click');
        await cohortForm(page).name.setValue('DTS 2025');
        await cohortForm(page).status.setValue('disabled');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('A cohort with this name already exists');
        expect(cohortNames(page, 0)).toContain('DTS 2026');
        expect(findButton(page, 'Disable DTS 2026').text()).toBe('Disable');
    });

    /**
     * With no message from PHP, a plain one shows instead (error)
     */
    it('shows a plain message when saving fails with no reply', async () => {
        // Arrange
        createCohort.mockRejectedValue(new Error('Network Error'));
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await cohortForm(page).name.setValue('New Cohort');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Unable to save the cohort');
    });

    /**
     * Fixing the name and saving again clears the old problem (edge)
     */
    it('clears the problem when the form is saved again', async () => {
        // Arrange
        createCohort.mockRejectedValueOnce({ response: { data: { error: 'A cohort with this name already exists' } } });
        createCohort.mockResolvedValueOnce({
            cohortID: 'cohort-new',
            cohortName: 'New Cohort',
            createdDate: '2026-11-03',
            isActive: true,
            students: [],
            subjects: [],
        });
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await cohortForm(page).name.setValue('DTS 2026');
        await cohortForm(page).form.trigger('submit');
        await flushPromises();
        await cohortForm(page).name.setValue('New Cohort');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(createCohort).toHaveBeenCalledTimes(2);
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(page.find('[role="alert"]').exists()).toBe(false);
    });

    /**
     * The note on what disabling means only shows while Disabled is chosen (boundary)
     */
    it('only shows the disabled note while Disabled is chosen', async () => {
        // Arrange
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');

        // Act
        await cohortForm(page).status.setValue('disabled');
        const noteWhenDisabled = page.find('#cohortStatusNote').exists();
        const describedBy = cohortForm(page).status.attributes('aria-describedby');
        await cohortForm(page).status.setValue('enabled');

        // Assert
        expect(noteWhenDisabled).toBe(true);
        expect(describedBy).toBe('cohortStatusNote');
        expect(page.find('#cohortStatusNote').exists()).toBe(false);
    });

    /**
     * The save button is a real submit button, so pressing Enter in the name box saves too (normal)
     */
    it('connects the save button to the form', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await page.get('.cohortCreateButton').trigger('click');

        // Assert
        expect(cohortForm(page).saveButton.attributes('type')).toBe('submit');
        expect(cohortForm(page).saveButton.attributes('form')).toBe('cohortForm');
    });

    /**
     * Saving twice before PHP replies is stopped, as the buttons are disabled while saving (edge)
     */
    it('disables the form buttons while saving', async () => {
        // Arrange
        createCohort.mockReturnValue(new Promise(() => {}));
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await cohortForm(page).name.setValue('New Cohort');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(cohortForm(page).saveButton.attributes('disabled')).toBeDefined();
        expect(page.get('.cohortDialogButtons .cohortSecondaryButton').attributes('disabled')).toBeDefined();
    });

    /**
     * The action buttons sit in a div inside a normal cell, a flex td doesn't centre with its row (normal)
     */
    it('keeps the action cell as a normal table cell', async () => {
        // Arrange and Act
        const page = await openPage();

        // Assert
        const actions = findButton(page, 'Edit DTS 2026').element.parentElement;
        expect(actions.tagName).toBe('DIV');
        expect(actions.classList.contains('cohortActions')).toBe(true);
        expect(actions.parentElement.tagName).toBe('TD');
        expect(actions.parentElement.classList.contains('cohortActions')).toBe(false);
    });

    /**
     * A refused name marks the name box as wrong and links it to the problem (normal)
     */
    it('marks the name as wrong when PHP refuses it', async () => {
        // Arrange
        updateCohort.mockRejectedValue({
            response: { data: { error: 'A cohort with this name already exists', field: 'cohortName' } },
        });
        const page = await openPage();
        await findButton(page, 'Edit DTS 2026').trigger('click');
        await cohortForm(page).name.setValue('DTS 2025');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(cohortForm(page).name.attributes('aria-invalid')).toBe('true');
        expect(cohortForm(page).name.attributes('aria-describedby')).toBe('cohortNameError');
    });

    /**
     * A lost connection isn't the name's fault, so the box isn't marked as wrong (error)
     */
    it('does not mark the name as wrong when the connection fails', async () => {
        // Arrange
        createCohort.mockRejectedValue(new Error('Network Error'));
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await cohortForm(page).name.setValue('New Cohort');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Unable to save the cohort');
        expect(cohortForm(page).name.attributes('aria-invalid')).toBeUndefined();
        expect(cohortForm(page).name.attributes('aria-describedby')).toBeUndefined();
    });

    /**
     * A server error that isn't about the name doesn't mark the box either (error)
     */
    it('does not mark the name as wrong for a server error', async () => {
        // Arrange
        updateCohort.mockRejectedValue({ response: { status: 500, data: { error: 'Unable to update the cohort' } } });
        const page = await openPage();
        await findButton(page, 'Edit DTS 2026').trigger('click');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Unable to update the cohort');
        expect(cohortForm(page).name.attributes('aria-invalid')).toBeUndefined();
    });

    /**
     * Fixing the name clears the mark, even if the next save fails for another reason (edge)
     */
    it('clears the wrong name mark on the next save', async () => {
        // Arrange
        createCohort.mockRejectedValueOnce({
            response: { data: { error: 'A cohort with this name already exists', field: 'cohortName' } },
        });
        createCohort.mockRejectedValueOnce(new Error('Network Error'));
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await cohortForm(page).name.setValue('DTS 2026');
        await cohortForm(page).form.trigger('submit');
        await flushPromises();
        const markedAfterRefusal = cohortForm(page).name.attributes('aria-invalid');
        await cohortForm(page).name.setValue('New Cohort');

        // Act
        await cohortForm(page).form.trigger('submit');
        await flushPromises();

        // Assert
        expect(markedAfterRefusal).toBe('true');
        expect(cohortForm(page).name.attributes('aria-invalid')).toBeUndefined();
    });

    /**
     * Opens a cohort's students and the add students pop-up, DTS 2026 is the first cohort
     */
    async function openAddStudentsDialog() {
        getStudentsOutsideCohort.mockResolvedValue([
            { studentNumber: 'ws382590', name: 'Liam Evans', currentCohort: 'BSc Computing 2025' },
            { studentNumber: 'ws382602', name: 'Grace Okafor', currentCohort: 'DTS 2025' },
        ]);
        const page = await openPage();
        await page.findAll('.cohortTable')[0].get('[aria-label="Show details for DTS 2026"]').trigger('click');
        await findButton(page, 'Add students to DTS 2026').trigger('click');
        await flushPromises();

        return page;
    }

    async function openMoveDialog() {
        const page = await openPage();
        await page.findAll('.cohortTable')[0].get('[aria-label="Show details for DTS 2026"]').trigger('click');
        await findButton(page, 'Remove from cohort: Joe Bloggs').trigger('click');

        return page;
    }

    /**
     * PHP's reply after moving students, the student has left DTS 2026 and joined DTS 2025
     */
    function cohortsAfterMove() {
        const cohorts = structuredClone(sampleCohorts);
        const student = cohorts[0].students.shift();
        cohorts.find((cohort) => cohort.cohortName === 'DTS 2025').students.push(student);

        return cohorts;
    }

    /**
     * The pop-up lists students with their current cohort and the note about moving (normal)
     */
    it('lists students in other cohorts in the add pop-up', async () => {
        // Arrange and Act
        const page = await openAddStudentsDialog();

        // Assert
        expect(getStudentsOutsideCohort).toHaveBeenCalledWith('cohort-1');
        expect(page.get('.popupTitle').text()).toBe('Add Students to DTS 2026');
        expect(page.get('.cohortDialogTable tbody tr').text()).toContain('BSc Computing 2025');
        expect(page.get('.popupBody').text()).toContain('A student can only be in one cohort');
    });

    /**
     * Search works on both the name and the student number (normal)
     */
    it('searches by name or student number', async () => {
        // Arrange
        const page = await openAddStudentsDialog();
        const options = () => page.findAll('.cohortDialogOption').map((option) => option.text());

        // Act
        await page.get('#addStudentsSearch').setValue('GRACE');
        const byName = options();
        await page.get('#addStudentsSearch').setValue('382590');
        const byNumber = options();

        // Assert
        expect(byName).toEqual(['Grace Okafor ws382602']);
        expect(byNumber).toEqual(['Liam Evans ws382590']);
    });

    /**
     * The button counts what's chosen, and only says Student for one (boundary)
     */
    it('counts the chosen students on the add button', async () => {
        // Arrange
        const page = await openAddStudentsDialog();
        const options = page.findAll('.cohortDialogOption input');
        const addButton = page.get('.cohortDialogButtons .cohortPrimaryButton');
        const labelWithNone = addButton.text();
        const disabledWithNone = addButton.attributes('disabled');

        // Act
        await options[0].setValue(true);
        const labelWithOne = addButton.text();
        await options[1].setValue(true);

        // Assert
        expect(labelWithNone).toBe('Add Students');
        expect(disabledWithNone).toBeDefined();
        expect(labelWithOne).toBe('Add 1 Student');
        expect(addButton.text()).toBe('Add 2 Students');
    });

    /**
     * Every ticked student is sent in one go, not just the first or last one (normal)
     */
    it('adds every chosen student at once', async () => {
        // Arrange
        moveStudents.mockResolvedValue(structuredClone(sampleCohorts));
        const page = await openAddStudentsDialog();
        const options = page.findAll('.cohortDialogOption input');
        await options[0].setValue(true);
        await options[1].setValue(true);

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(moveStudents).toHaveBeenCalledTimes(1);
        expect(moveStudents).toHaveBeenCalledWith('cohort-1', ['ws382590', 'ws382602']);
    });

    /**
     * Adding saves the chosen students and updates the cohorts they moved between (normal)
     */
    it('adds the chosen students and updates both cohorts', async () => {
        // Arrange
        const cohortsWithNewStudent = structuredClone(sampleCohorts);
        cohortsWithNewStudent[0].students.push({
            studentNumber: 'ws382590',
            name: 'Liam Evans',
            email: 'a@b.c',
            lastLogin: null,
            status: 'Active',
        });
        cohortsWithNewStudent[1].students.pop();
        moveStudents.mockResolvedValue(cohortsWithNewStudent);
        const page = await openAddStudentsDialog();
        await page.findAll('.cohortDialogOption input')[0].setValue(true);

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(moveStudents).toHaveBeenCalledWith('cohort-1', ['ws382590']);
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(page.get('.cohortDetails h3').text()).toContain(`(${cohortsWithNewStudent[0].students.length})`);
    });

    /**
     * Moving students keeps the table on the same page (boundary)
     */
    it('stays on the same page after moving a student', async () => {
        // Arrange
        moveStudents.mockResolvedValue(cohortsAfterMove());
        const page = await openMoveDialog();
        await page.get('#newCohort').setValue('cohort-5');
        const summary = page.findAll('.cohortPagingSummary')[0].text();

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(page.findAll('.cohortPagingSummary')[0].text()).toBe(summary);
    });

    /**
     * The pop-up stays open and shows the problem if adding fails (error)
     */
    it('shows a problem when adding students fails', async () => {
        // Arrange
        moveStudents.mockRejectedValue({ response: { data: { error: 'Reload the page and try again' } } });
        const page = await openAddStudentsDialog();
        await page.findAll('.cohortDialogOption input')[0].setValue(true);

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Reload the page and try again');
    });

    /**
     * A problem loading the students shows in the pop-up (error)
     */
    it('shows a problem when the students cannot load', async () => {
        // Arrange
        const page = await openPage();
        getStudentsOutsideCohort.mockRejectedValue(new Error('Network Error'));
        await page.findAll('.cohortTable')[0].get('[aria-label="Show details for DTS 2026"]').trigger('click');

        // Act
        await findButton(page, 'Add students to DTS 2026').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Unable to load the students');
    });

    /**
     * Nobody to add says so, instead of an empty table (boundary)
     */
    it('says when there are no students to add', async () => {
        // Arrange
        const page = await openPage();
        getStudentsOutsideCohort.mockResolvedValue([]);
        await page.findAll('.cohortTable')[0].get('[aria-label="Show details for DTS 2026"]').trigger('click');

        // Act
        await findButton(page, 'Add students to DTS 2026').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.popupBody').text()).toContain('There are no students in other cohorts to add');
    });

    /**
     * Adding to a disabled cohort warns that the students will be locked out (edge)
     */
    it('warns when adding students to a disabled cohort', async () => {
        // Arrange
        getStudentsOutsideCohort.mockResolvedValue([
            { studentNumber: 'ws382590', name: 'Liam Evans', currentCohort: 'DTS 2026' },
        ]);
        const page = await openPage();
        await page.findAll('select')[0].setValue('disabled');
        await page
            .findAll('.cohortTable')[0]
            .get('[aria-label="Show details for Software Engineering 2024"]')
            .trigger('click');

        // Act
        await findButton(page, 'Add students to Software Engineering 2024').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.popupBody').text()).toContain("won't be able to log in");
    });

    /**
     * The remove pop-up names the student and offers every other cohort (normal)
     */
    it('asks where the student moves to', async () => {
        // Arrange and Act
        const page = await openMoveDialog();

        // Assert
        expect(page.get('.popupTitle').text()).toBe('Remove from Cohort');
        expect(page.get('.cohortDialogStudent').text()).toBe('Joe Bloggs (WS382544)');
        const choices = page.findAll('#newCohort option').map((option) => option.text());
        expect(choices).toContain('Select a cohort');
        expect(choices).toContain('DTS 2025');
        expect(choices).toContain('DTS 2024 (disabled)');
        expect(choices).not.toContain('DTS 2026');
    });

    /**
     * Every student needs a cohort, so nothing happens without one chosen (error)
     */
    it('needs a new cohort before moving', async () => {
        // Arrange
        const page = await openMoveDialog();

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(moveStudents).not.toHaveBeenCalled();
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Select a cohort to move this student to');
    });

    /**
     * Moving sends the student to the new cohort and updates both cohorts on the page (normal)
     */
    it('moves the student to the chosen cohort', async () => {
        // Arrange
        moveStudents.mockResolvedValue(cohortsAfterMove());
        const page = await openMoveDialog();
        await page.get('#newCohort').setValue('cohort-5');

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(moveStudents).toHaveBeenCalledWith('cohort-5', ['WS382544']);
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(page.get('.cohortDetailsTable').text()).not.toContain('Joe Bloggs');
    });

    /**
     * Cancel leaves the student where they are (edge)
     */
    it('leaves the student in place when cancelled', async () => {
        // Arrange
        const page = await openMoveDialog();

        // Act
        await page.get('.cohortDialogButtons .cohortSecondaryButton').trigger('click');

        // Assert
        expect(moveStudents).not.toHaveBeenCalled();
        expect(page.find('.popupDialog').exists()).toBe(false);
        expect(page.get('.cohortDetailsTable').text()).toContain('Joe Bloggs');
    });

    /**
     * Choosing a disabled cohort warns that the student will be locked out (edge)
     */
    it('warns when moving a student to a disabled cohort', async () => {
        // Arrange
        const page = await openMoveDialog();

        // Act
        await page.get('#newCohort').setValue('cohort-8');

        // Assert
        expect(page.get('.popupBody').text()).toContain("won't be able to log in");
    });

    /**
     * With only one cohort there's nowhere to move to, so the button can't be used (boundary)
     */
    it('stops the move when there are no other cohorts', async () => {
        // Arrange
        getCohorts.mockResolvedValue(structuredClone(sampleCohorts).slice(0, 1));

        // Act
        const page = await openMoveDialog();

        // Assert
        expect(page.get('.popupBody').text()).toContain('There are no other cohorts');
        expect(page.get('.cohortDialogButtons .cohortPrimaryButton').attributes('disabled')).toBeDefined();
    });

    /**
     * The pop-up stays open and the student stays listed if moving fails (error)
     */
    it('shows a problem when moving fails', async () => {
        // Arrange
        moveStudents.mockRejectedValue(new Error('Network Error'));
        const page = await openMoveDialog();
        await page.get('#newCohort').setValue('cohort-5');

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Unable to move Joe Bloggs');
        expect(page.get('.cohortDetailsTable').text()).toContain('Joe Bloggs');
    });

    /**
     * The remove buttons have the same look as the rest of the site (normal)
     */
    it('styles the remove buttons like the rest of the site', async () => {
        // Arrange
        const page = await openPage();

        // Act
        await page.findAll('.cohortTable')[0].get('[aria-label="Show details for DTS 2026"]').trigger('click');
        await page.findAll('.cohortTable')[1].get('[aria-label="Show details for DTS 2026"]').trigger('click');

        // Assert
        expect(findButton(page, 'Remove from cohort: Joe Bloggs').classes()).toContain('btn-outline-danger');
        expect(findButton(page, 'Remove from cohort: Web Applications').classes()).toContain('btn-outline-danger');
    });

    /**
     * Searching narrows the list without losing who's already chosen (edge)
     */
    it('keeps a chosen student when a search hides them', async () => {
        // Arrange
        const page = await openAddStudentsDialog();
        await page.findAll('.cohortDialogOption input')[0].setValue(true);

        // Act
        await page.get('#addStudentsSearch').setValue('grace');

        // Assert
        expect(page.findAll('.cohortDialogOption').map((option) => option.text())).toEqual(['Grace Okafor ws382602']);
        expect(page.get('.cohortDialogButtons .cohortPrimaryButton').text()).toBe('Add 1 Student');
    });

    /**
     * The cohort dropdown lists each current cohort once, and shows only the students in it (normal)
     */
    it('filters the students by their current cohort', async () => {
        // Arrange
        const page = await openAddStudentsDialog();
        const filter = page.get('#addStudentsCohortFilter');

        // Act
        await filter.setValue('DTS 2025');

        // Assert
        expect(filter.findAll('option').map((option) => option.text())).toEqual([
            'All cohorts',
            'BSc Computing 2025',
            'DTS 2025',
        ]);
        expect(page.findAll('.cohortDialogOption').map((option) => option.text())).toEqual(['Grace Okafor ws382602']);
    });

    /**
     * The search and the cohort dropdown work together (edge)
     */
    it('combines the search with the cohort filter', async () => {
        // Arrange
        const page = await openAddStudentsDialog();
        await page.get('#addStudentsCohortFilter').setValue('DTS 2025');

        // Act
        await page.get('#addStudentsSearch').setValue('liam');

        // Assert
        expect(page.findAll('.cohortDialogOption')).toHaveLength(0);
        expect(page.get('.cohortDialogTable tbody').text()).toBe('No students match your search or filter');
    });

    /**
     * A search with no match says so, rather than showing an empty table (edge)
     */
    it('says when no student matches the search', async () => {
        // Arrange
        const page = await openAddStudentsDialog();

        // Act
        await page.get('#addStudentsSearch').setValue('zzz');

        // Assert
        expect(page.get('.cohortDialogTable tbody').text()).toBe('No students match your search or filter');
    });

    /**
     * Cancel closes the add pop-up without moving anyone (edge)
     */
    it('moves nobody when adding is cancelled', async () => {
        // Arrange
        const page = await openAddStudentsDialog();
        await page.findAll('.cohortDialogOption input')[0].setValue(true);

        // Act
        await page.get('.cohortDialogButtons .cohortSecondaryButton').trigger('click');

        // Assert
        expect(moveStudents).not.toHaveBeenCalled();
        expect(page.find('.popupDialog').exists()).toBe(false);
    });

    /**
     * An active cohort has no lock out warning (boundary)
     */
    it('does not warn when adding students to an active cohort', async () => {
        // Arrange and Act
        const page = await openAddStudentsDialog();

        // Assert
        expect(page.get('.popupBody').text()).not.toContain("won't be able to log in");
    });

    /**
     * Saving twice before PHP replies is stopped, as the buttons are disabled while saving (edge)
     */
    it('disables the add buttons while saving', async () => {
        // Arrange
        moveStudents.mockReturnValue(new Promise(() => {}));
        const page = await openAddStudentsDialog();
        await page.findAll('.cohortDialogOption input')[0].setValue(true);

        // Act
        await page.get('.cohortDialogButtons .cohortPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(page.get('.cohortDialogButtons .cohortPrimaryButton').attributes('disabled')).toBeDefined();
        expect(page.get('.cohortDialogButtons .cohortSecondaryButton').attributes('disabled')).toBeDefined();
    });

    /**
     * The warning goes when an active cohort is chosen instead (boundary)
     */
    it('only warns while a disabled cohort is chosen', async () => {
        // Arrange
        const page = await openMoveDialog();
        await page.get('#newCohort').setValue('cohort-8');
        const warnedForDisabled = page.get('.popupBody').text().includes("won't be able to log in");

        // Act
        await page.get('#newCohort').setValue('cohort-5');

        // Assert
        expect(warnedForDisabled).toBe(true);
        expect(page.get('.popupBody').text()).not.toContain("won't be able to log in");
    });

    /**
     * The problem is linked to the box, so screen readers hear it with the field (normal)
     */
    it('links the problem to the new cohort box', async () => {
        // Arrange
        const page = await openMoveDialog();

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('#newCohort').attributes('aria-invalid')).toBe('true');
        expect(page.get('#newCohort').attributes('aria-describedby')).toBe('moveStudentError');
        expect(page.find('#moveStudentError').exists()).toBe(true);
    });

    /**
     * Choosing a cohort and saving again clears the old problem (edge)
     */
    it('clears the problem once a cohort is chosen and saved', async () => {
        // Arrange
        moveStudents.mockResolvedValue(cohortsAfterMove());
        const page = await openMoveDialog();
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();
        await page.get('#newCohort').setValue('cohort-5');

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(page.find('[role="alert"]').exists()).toBe(false);
        expect(page.find('.popupDialog').exists()).toBe(false);
    });

    /**
     * The save button is a real submit button, so pressing Enter works too (normal)
     */
    it('connects the remove and move button to the form', async () => {
        // Arrange and Act
        const page = await openMoveDialog();

        // Assert
        const moveButton = page.get('.cohortDialogButtons .cohortPrimaryButton');
        expect(moveButton.text()).toBe('Remove and Move');
        expect(moveButton.attributes('type')).toBe('submit');
        expect(moveButton.attributes('form')).toBe('moveStudentForm');
    });

    /**
     * A cohort created this visit can be chosen straight away (edge)
     */
    it('offers a newly created cohort as somewhere to move to', async () => {
        // Arrange
        createCohort.mockResolvedValue({
            cohortID: 'cohort-new',
            cohortName: 'New Cohort',
            createdDate: '2026-11-03',
            isActive: true,
            students: [],
            subjects: [],
        });
        const page = await openPage();
        await page.get('.cohortCreateButton').trigger('click');
        await page.get('#cohortName').setValue('New Cohort');
        await page.get('#cohortForm').trigger('submit');
        await flushPromises();
        await page.findAll('.cohortTable')[0].get('[aria-label="Show details for DTS 2026"]').trigger('click');

        // Act
        await findButton(page, 'Remove from cohort: Joe Bloggs').trigger('click');

        // Assert
        expect(page.findAll('#newCohort option').map((option) => option.text())).toContain('New Cohort');
    });

    /**
     * A lost connection isn't the cohort's fault, so the box isn't marked as wrong (error)
     */
    it('does not mark the new cohort as wrong when the connection fails', async () => {
        // Arrange
        moveStudents.mockRejectedValue(new Error('Network Error'));
        const page = await openMoveDialog();
        await page.get('#newCohort').setValue('cohort-5');

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('Unable to move Joe Bloggs');
        expect(page.get('#newCohort').attributes('aria-invalid')).toBeUndefined();
        expect(page.get('#newCohort').attributes('aria-describedby')).toBeUndefined();
    });

    /**
     * A student someone else has already moved is about the student, not the cohort chosen (error)
     */
    it('does not mark the new cohort as wrong when the student can no longer be moved', async () => {
        // Arrange
        moveStudents.mockRejectedValue({
            response: { status: 400, data: { error: 'One of these students can no longer be moved here' } },
        });
        const page = await openMoveDialog();
        await page.get('#newCohort').setValue('cohort-5');

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('.popupDialog [role="alert"]').text()).toContain('can no longer be moved here');
        expect(page.get('#newCohort').attributes('aria-invalid')).toBeUndefined();
    });

    /**
     * A cohort deleted since the page loaded is the chosen cohort's problem, so the box is marked (normal)
     */
    it('marks the new cohort as wrong when it no longer exists', async () => {
        // Arrange
        moveStudents.mockRejectedValue({
            response: { status: 404, data: { error: 'Cohort not found', field: 'cohortID' } },
        });
        const page = await openMoveDialog();
        await page.get('#newCohort').setValue('cohort-5');

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(page.get('#newCohort').attributes('aria-invalid')).toBe('true');
        expect(page.get('#newCohort').attributes('aria-describedby')).toBe('moveStudentError');
    });

    /**
     * Choosing a cohort clears the mark, even if the next save fails for another reason (edge)
     */
    it('clears the wrong cohort mark on the next save', async () => {
        // Arrange
        moveStudents.mockRejectedValue(new Error('Network Error'));
        const page = await openMoveDialog();
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();
        const markedWithNoneChosen = page.get('#newCohort').attributes('aria-invalid');
        await page.get('#newCohort').setValue('cohort-5');

        // Act
        await page.get('#moveStudentForm').trigger('submit');
        await flushPromises();

        // Assert
        expect(markedWithNoneChosen).toBe('true');
        expect(page.get('#newCohort').attributes('aria-invalid')).toBeUndefined();
    });
});
