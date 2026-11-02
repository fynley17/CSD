// @vitest-environment jsdom

// Tests for the subjects table on the admin dashboard
// laid out Arrange (what PHP sends), Act (mount the table), Assert (check the screen)
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectTable from '../components/tables/subjectTable.vue';
import { getSubjects } from '../services/subjectService';

vi.mock('../services/subjectService', () => ({
    getSubjects: vi.fn(),
}));

/**
 * Mounts the table, router-link swapped for a plain <a> as there's no router here
 */
function openTable() {
    return mount(SubjectTable, { global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } } });
}

/**
 * Text of every cell, row by row
 */
function rowText(table) {
    return table.findAll('tbody tr').map((row) => row.findAll('td').map((cell) => cell.text()));
}

describe('SubjectTable', () => {
    beforeEach(() => {
        vi.mocked(getSubjects).mockReset();
    });

    /**
     * Each subject shows its name, cohort, tests, lecturer and status (normal)
     */
    it('shows a row for each subject', async () => {
        // Arrange
        vi.mocked(getSubjects).mockResolvedValue([
            {
                subjectName: 'Test Databases',
                cohortName: 'Test Cohort',
                testCount: 2,
                lecturerNames: 'Sample Lecturer, Test Teacher',
                isActive: true,
            },
            {
                subjectName: 'Test Archived',
                cohortName: 'Test Cohort',
                testCount: 0,
                lecturerNames: null,
                isActive: false,
            },
        ]);

        // Act
        const table = openTable();
        await flushPromises();

        // Assert
        expect(rowText(table)).toEqual([
            ['Test Databases', 'Test Cohort', '2', 'Sample Lecturer, Test Teacher', 'Active'],
            ['Test Archived', 'Test Cohort', '0', 'Not assigned', 'Archived'],
        ]);
    });

    /**
     * Keeps PHP's order so archived ones stay at the bottom (boundary)
     */
    it("keeps PHP's order, so archived subjects stay at the bottom", async () => {
        // Arrange
        vi.mocked(getSubjects).mockResolvedValue([
            {
                subjectName: 'Test Web Apps',
                cohortName: 'Test Cohort',
                testCount: 0,
                lecturerNames: null,
                isActive: true,
            },
            {
                subjectName: 'Test Archived',
                cohortName: 'Test Cohort',
                testCount: 0,
                lecturerNames: null,
                isActive: false,
            },
        ]);

        // Act
        const table = openTable();
        await flushPromises();

        // Assert
        expect(rowText(table).map((row) => row[0])).toEqual(['Test Web Apps', 'Test Archived']);
    });

    /**
     * No subjects yet, the table says so (boundary)
     */
    it('says when there are no subjects', async () => {
        // Arrange
        vi.mocked(getSubjects).mockResolvedValue([]);

        // Act
        const table = openTable();
        await flushPromises();

        // Assert
        expect(rowText(table)).toEqual([['No subjects to show yet']]);
    });

    /**
     * Loading message until PHP answers (boundary)
     */
    it('says it is loading before PHP answers', () => {
        // Arrange
        // promise that never finishes will stay on loading
        vi.mocked(getSubjects).mockReturnValue(new Promise(() => {}));

        // Act
        const table = openTable();

        // Assert
        expect(table.get('[role="status"]').text()).toBe('Loading subjects');
        expect(table.find('table').exists()).toBe(false);
    });

    /**
     * Request fails, the problem box shows and the table doesn't (error)
     */
    it('shows a problem message when the subjects cannot be loaded', async () => {
        // Arrange
        vi.mocked(getSubjects).mockRejectedValue(new Error('Network Error'));

        // Act
        const table = openTable();
        await flushPromises();

        // Assert
        expect(table.get('[role="alert"]').text()).toBe('There is a problem: Unable to load the subjects');
        expect(table.find('table').exists()).toBe(false);
    });

    /**
     * HTML in a subject or lecturer name just shows as text (error)
     */
    it('shows HTML in a subject or lecturer name as text', async () => {
        // Arrange
        vi.mocked(getSubjects).mockResolvedValue([
            {
                subjectName: '<img src=x id=injected>',
                cohortName: 'Test Cohort',
                testCount: 0,
                lecturerNames: '<img src=x id=injectedLecturer>',
                isActive: true,
            },
        ]);

        // Act
        const table = openTable();
        await flushPromises();

        // Assert
        expect(table.find('#injected').exists()).toBe(false);
        expect(table.find('#injectedLecturer').exists()).toBe(false);
        expect(rowText(table)[0][0]).toBe('<img src=x id=injected>');
        expect(rowText(table)[0][3]).toBe('<img src=x id=injectedLecturer>');
    });
});
