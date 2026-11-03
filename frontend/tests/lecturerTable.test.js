// @vitest-environment jsdom

// Tests for the lecturers table, both the admin dashboard one and the management page one
// covers lecturers coming back, none at all, still loading, the request failing, HTML in a name
// and the management page buttons and status filter
// laid out Arrange (what PHP sends back), Act (mount the table), Assert (check what's on screen)
import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import LecturerTable from '../components/tables/lecturerTable.vue';
import { getLecturers, getRecentLecturers } from '../services/administratorService';

// fake service, these tests don't need PHP or the database running
vi.mock('../services/administratorService', () => ({
    getRecentLecturers: vi.fn(),
    getLecturers: vi.fn(),
}));

/**
 * Mounts the table, router-link becomes a plain <a> as there's no router in these tests
 * props only gets used by the management page tests
 */
function openTable(props = {}) {
    return mount(LecturerTable, { props, global: { stubs: { RouterLink: { template: '<a><slot /></a>' } } } });
}

/**
 * Pulls the text out of each cell, so the tests can check it without worrying about the HTML
 */
function rowText(table) {
    return table.findAll('tbody tr').map((row) => row.findAll('td').map((cell) => cell.text()));
}

// one lecturer who can log in and one who's disabled, both management page tests use these
const manageLecturers = [
    {
        userID: 'lecturer-1',
        name: 'Test Lecturer',
        staffID: 'ws000101',
        email: 'ws000101@example.test',
        isEnabled: true,
        lastLogin: null,
    },
    {
        userID: 'lecturer-2',
        name: 'Second Lecturer',
        staffID: 'ws000102',
        email: 'ws000102@example.test',
        isEnabled: false,
        lastLogin: null,
    },
];

describe('LecturerTable', () => {
    // wipes whatever the fake returned in the last test
    beforeEach(() => {
        vi.mocked(getRecentLecturers).mockReset();
        vi.mocked(getLecturers).mockReset();
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
     * Before PHP answers there's a loading message and no table yet (boundary)
     */
    it('says it is loading before PHP answers', () => {
        // Arrange
        // this promise never resolves, keeps the table stuck on loading
        vi.mocked(getRecentLecturers).mockReturnValue(new Promise(() => {}));

        // Act
        const table = openTable();

        // Assert
        expect(table.get('[role="status"]').text()).toBe('Loading lecturers');
        expect(table.find('table').exists()).toBe(false);
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

    /**
     * A name with HTML in it shows as plain text, so it can't add anything to the page (error)
     */
    it('shows HTML in a name as text', async () => {
        // Arrange
        vi.mocked(getRecentLecturers).mockResolvedValue([
            {
                name: '<img src=x id=injected>',
                staffID: 'ws000101',
                email: 'ws000101@example.test',
                isEnabled: true,
                lastLogin: null,
            },
        ]);

        // Act
        const table = openTable();
        await flushPromises();

        // Assert
        expect(table.find('#injected').exists()).toBe(false);
        expect(rowText(table)[0][0]).toBe('<img src=x id=injected>');
    });

    /**
     * The management page asks for every lecturer, not the dashboard's 5. Each row gets its buttons and pressing
     * one tells the page which lecturer it was (normal)
     */
    it('shows the action buttons in manage mode and sends the lecturer to the page', async () => {
        // Arrange
        vi.mocked(getLecturers).mockResolvedValue(manageLecturers);
        const table = openTable({ manage: true });
        await flushPromises();

        // Act
        await table.get('[aria-label="Edit Test Lecturer"]').trigger('click');
        await table.get('[aria-label="Disable account for Test Lecturer"]').trigger('click');
        await table.get('[aria-label="Delete Test Lecturer"]').trigger('click');

        // Assert
        expect(getRecentLecturers).not.toHaveBeenCalled();
        expect(table.get('[aria-label="Enable account for Second Lecturer"]').text()).toBe('Enable account');
        expect(table.emitted('edit')[0]).toEqual([manageLecturers[0]]);
        expect(table.emitted('toggle')[0]).toEqual([manageLecturers[0]]);
        expect(table.emitted('delete')[0]).toEqual([manageLecturers[0]]);
    });

    /**
     * The status dropdown set to disabled leaves out the lecturers who can still log in (boundary)
     */
    it('only shows disabled lecturers when the filter says disabled', async () => {
        // Arrange
        vi.mocked(getLecturers).mockResolvedValue(manageLecturers);

        // Act
        const table = openTable({ manage: true, statusFilter: 'disabled' });
        await flushPromises();

        // Assert
        expect(rowText(table).map((row) => row[0])).toEqual(['Second Lecturer']);
    });
});
