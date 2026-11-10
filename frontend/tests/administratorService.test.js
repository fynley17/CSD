// Tests for administratorService.js, the file the admin dashboard uses to ask PHP for its totals
// checks it asks the right address and hands back just the totals, or passes a failed request on to the page
// each test is laid out Arrange (pick what PHP sends back), Act (call the service), Assert (check the answer)
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import {
    createLecturer,
    deleteLecturer,
    getDashboardSummary,
    getLecturers,
    getRecentLecturers,
    resendSetupLink,
    setLecturerEnabled,
    updateLecturer,
} from '../services/administratorService';

// swaps the real axios setup for a pretend one, so no PHP is needed
vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('administratorService', () => {
    // clears what the last test asked for, so each test starts fresh
    beforeEach(() => {
        vi.clearAllMocks();
    });

    /**
     * Asks the admin totals address and gives back only the totals, not the whole axios reply (normal)
     */
    it('gets the totals from the admin dashboard summary address', async () => {
        // Arrange
        const totals = { totalSubjects: 8, totalLecturers: 12, archivedSubjects: 2, disabledLecturers: 1 };
        vi.mocked(api.get).mockResolvedValue({ data: totals });

        // Act
        const summary = await getDashboardSummary();

        // Assert
        expect(api.get).toHaveBeenCalledWith('/admin/dashboard-summary');
        expect(summary).toEqual(totals);
    });

    /**
     * A refused or failed request isn't hidden, the page gets it so it can show the message (error)
     */
    it('passes a failed request on to the page', async () => {
        // Arrange
        const refused = { response: { status: 403, data: { error: 'You do not have permission to view this page' } } };
        vi.mocked(api.get).mockRejectedValue(refused);

        // Act
        const request = getDashboardSummary();

        // Assert
        await expect(request).rejects.toBe(refused);
    });

    /**
     * Calls /admin/recent-lecturers and hands back response.data (normal)
     */
    it('gets the lecturers from the recent lecturers address', async () => {
        // Arrange
        const lecturers = [
            {
                name: 'Test Lecturer',
                staffID: 'ws000101',
                email: 'ws000101@example.test',
                isEnabled: true,
                lastLogin: '2026-10-20 09:00:00',
            },
        ];
        vi.mocked(api.get).mockResolvedValue({ data: lecturers });

        // Act
        const recentLecturers = await getRecentLecturers();

        // Assert
        expect(api.get).toHaveBeenCalledWith('/admin/recent-lecturers');
        expect(recentLecturers).toEqual(lecturers);
    });

    /**
     * The management page asks for every lecturer, not the dashboard's 5 (normal)
     */
    it('gets every lecturer from the lecturers address', async () => {
        // Arrange
        const lecturers = [{ userID: 'lecturer-1', name: 'Test Lecturer', staffID: 'L1001', isEnabled: true }];
        vi.mocked(api.get).mockResolvedValue({ data: lecturers });

        // Act
        const allLecturers = await getLecturers();

        // Assert
        expect(api.get).toHaveBeenCalledWith('/admin/lecturers');
        expect(allLecturers).toEqual(lecturers);
    });

    /**
     * Edit sends the form details to that lecturer's address with PUT (normal)
     */
    it('sends the edited details to the lecturer address', async () => {
        // Arrange
        const details = { firstName: 'Test', lastName: 'Lecturer', staffID: 'L1001', email: 'test@example.test' };
        vi.mocked(api.put).mockResolvedValue({ data: { message: 'Changes saved' } });

        // Act
        const reply = await updateLecturer('lecturer-1', details);

        // Assert
        expect(api.put).toHaveBeenCalledWith('/admin/lecturers/lecturer-1', details);
        expect(reply).toEqual({ message: 'Changes saved' });
    });

    /**
     * Disable sends isEnabled false, enable and Undo use the same call with true (normal)
     */
    it('sends isEnabled to the enabled address', async () => {
        // Arrange
        vi.mocked(api.patch).mockResolvedValue({ data: { message: 'Status updated' } });

        // Act
        await setLecturerEnabled('lecturer-1', false);

        // Assert
        expect(api.patch).toHaveBeenCalledWith('/admin/lecturers/lecturer-1/enabled', { isEnabled: false });
    });

    /**
     * A refused delete isn't hidden, the pop-up needs PHP's message to show it (error)
     */
    it('passes a refused delete on to the page', async () => {
        // Arrange
        const refused = {
            response: { status: 400, data: { error: "Administrator accounts can't be changed on this page" } },
        };
        vi.mocked(api.delete).mockRejectedValue(refused);

        // Act
        const request = deleteLecturer('admin-1');

        // Assert
        expect(api.delete).toHaveBeenCalledWith('/admin/lecturers/admin-1');
        await expect(request).rejects.toBe(refused);
    });

    /**
     * Add Lecturer posts what was typed to the lecturers address and gives back PHP's answer (normal)
     */
    it('posts a new lecturer and gives back the message', async () => {
        // Arrange
        const details = {
            firstName: 'New',
            lastName: 'Lecturer',
            staffID: 'L2001',
            email: 'new.lecturer@example.test',
            role: 'admin',
        };
        const reply = { message: 'Administrator added', emailSent: true, userID: 'new-id' };
        vi.mocked(api.post).mockResolvedValue({ data: reply });

        // Act
        const result = await createLecturer(details);

        // Assert
        expect(api.post).toHaveBeenCalledWith('/admin/lecturers', details);
        expect(result).toEqual(reply);
    });

    /**
     * Resend link puts the ID in the address safely, a slash can't turn it into a different address (boundary)
     */
    it('encodes the ID when resending a link', async () => {
        // Arrange
        vi.mocked(api.post).mockResolvedValue({ data: { message: 'Link sent' } });

        // Act
        await resendSetupLink('abc/def');

        // Assert
        expect(api.post).toHaveBeenCalledWith('/admin/lecturers/abc%2Fdef/setup-link');
    });
});
