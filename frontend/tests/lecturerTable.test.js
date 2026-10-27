// @vitest-environment jsdom

// Tests for the recent lecturers table on the admin dashboard
// covers lecturers coming back, none at all, still loading, and the request failing
// laid out Arrange (what PHP sends back), Act (mount the table), Assert (check what's on screen)
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LecturerTable from '../components/tables/lecturerTable.vue';
import { getRecentLecturers } from '../services/administratorService';

// fake service, these tests don't need PHP or the database running
vi.mock('../services/administratorService', () => ({
    getRecentLecturers: vi.fn(),
}));

/**
 * Mounts the table, router-link becomes a plain <a> as there's no router in these tests
 */
function openTable() {
    return mount(LecturerTable, { global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } } });
}

/**
 * Pulls the text out of each cell, i.e. [['Test Lecturer', 'ws000101', ...], ...]
 */
function rowText(table) {
    return table.findAll('tbody tr').map((row) => row.findAll('td').map((cell) => cell.text()));
}

describe('LecturerTable', () => {
    // wipes whatever the fake returned in the last test
    beforeEach(() => {
        vi.mocked(getRecentLecturers).mockReset();
    });

    /**
     * Two lecturers come back, one active and one disabled who's never logged in (normal)
     */
    it('shows a row for each lecturer', async () => {
        // Arrange
        vi.mocked(getRecentLecturers).mockResolvedValue([
            {
                name: 'Test Lecturer',
                staffID: 'ws000101',
                email: 'ws000101@example.test',
                isEnabled: true,
                lastLogin: '2026-01-15 15:17:00',
            },
            {
                name: 'Second Lecturer',
                staffID: 'ws000102',
                email: 'ws000102@example.test',
                isEnabled: false,
                lastLogin: null,
            },
        ]);

        // Act
        const table = openTable();
        await flushPromises();

        // Assert
        expect(rowText(table)).toEqual([
            ['Test Lecturer', 'ws000101', 'ws000101@example.test', 'Active', '15/01/2026, 15:17'],
            ['Second Lecturer', 'ws000102', 'ws000102@example.test', 'Disabled', 'Never'],
        ]);
    });

    /**
     * No lecturers at all, the table shows a message row (boundary)
     */
    it('says when there are no lecturers', async () => {
        // Arrange
        vi.mocked(getRecentLecturers).mockResolvedValue([]);

        // Act
        const table = openTable();
        await flushPromises();

        // Assert
        expect(rowText(table)).toEqual([['No lecturers to show yet']]);
    });

    /**
     * Before PHP answers there's a loading row (boundary)
     */
    it('says it is loading before PHP answers', () => {
        // Arrange
        // this promise never resolves, keeps the table stuck on loading
        vi.mocked(getRecentLecturers).mockReturnValue(new Promise(() => {}));

        // Act
        const table = openTable();

        // Assert
        expect(rowText(table)).toEqual([['Loading lecturers']]);
    });

    /**
     * The request fails, the problem box shows and the table doesn't (error)
     */
    it('shows a problem message when the lecturers cannot be loaded', async () => {
        // Arrange
        vi.mocked(getRecentLecturers).mockRejectedValue(new Error('Network Error'));

        // Act
        const table = openTable();
        await flushPromises();

        // Assert
        expect(table.get('[role="alert"]').text()).toBe('There is a problem: Unable to load the lecturers');
        expect(table.find('table').exists()).toBe(false);
    });
});
