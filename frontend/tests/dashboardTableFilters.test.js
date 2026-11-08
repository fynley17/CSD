// @vitest-environment jsdom

// Tests for the filter dropdowns on the lecturer dashboard tables
// laid out Arrange (what PHP sends back), Act (pick from the dropdown), Assert (check the rows)
import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import StudentTable from '../components/tables/studentTable.vue';
import TestTable from '../components/tables/testTable.vue';
import { getRecentStudentActivity } from '../services/lecturerService';
import { getLecturerTests } from '../services/testService';

vi.mock('../services/lecturerService', () => ({ getRecentStudentActivity: vi.fn() }));
vi.mock('../services/testService', () => ({ getLecturerTests: vi.fn() }));

const stubs = { RouterLink: { template: '<a><slot /></a>' } };

function firstCells(table) {
    return table.findAll('tbody tr').map((row) => row.find('td').text());
}

describe('Dashboard table filters', () => {
    /**
     * The student activity table filters by status (normal)
     */
    it('filters recent student activity by status', async () => {
        // Arrange
        getRecentStudentActivity.mockResolvedValue([
            { name: 'Joe Bloggs', studentNumber: 'WS1', email: 'a@b.c', status: 'Active', lastLogin: null },
            { name: 'Sarah Jones', studentNumber: 'WS2', email: 'd@e.f', status: 'Pending approval', lastLogin: null },
        ]);
        const table = mount(StudentTable, { global: { stubs } });
        await flushPromises();

        // Act
        await table.get('#dashboardStudentStatusFilter').setValue('Pending approval');

        // Assert
        expect(firstCells(table)).toEqual(['Sarah Jones']);
    });

    /**
     * The tests table lists each subject once, in order, and filters by it (normal)
     */
    it('filters all tests by subject', async () => {
        // Arrange
        getLecturerTests.mockResolvedValue([
            { testName: 'SQL Basics', subjectName: 'Databases', questionCount: 5, timeLimit: null },
            { testName: 'HTML Quiz', subjectName: 'Web Apps', questionCount: 4, timeLimit: 10 },
            { testName: 'Joins', subjectName: 'Databases', questionCount: 6, timeLimit: 20 },
        ]);
        const table = mount(TestTable, { global: { stubs } });
        await flushPromises();
        const select = table.get('#dashboardTestSubjectFilter');

        // Act
        await select.setValue('Databases');

        // Assert
        expect(select.findAll('option').map((option) => option.text())).toEqual([
            'All subjects',
            'Databases',
            'Web Apps',
        ]);
        expect(firstCells(table)).toEqual(['Joins', 'SQL Basics']);
    });
});
