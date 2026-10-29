// Tests for administratorService.js, the file the admin dashboard uses to ask PHP for its totals
// checks it asks the right address and hands back just the totals, or passes a failed request on to the page
// each test is laid out Arrange (pick what PHP sends back), Act (call the service), Assert (check the answer)
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { getDashboardSummary } from '../services/administratorService';

// swaps the real axios setup for a pretend one, so no PHP is needed
vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
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
});
