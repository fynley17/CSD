// Tests for cohortService.js
// laid out Arrange, Act, Assert
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { getCohorts, setCohortActive } from '../services/cohortService';

// pretend api.js, no PHP needed
vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        put: vi.fn(),
    },
}));

describe('cohortService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    /**
     * Calls /admin/cohorts and gives back the data (normal)
     */
    it('gets the cohorts', async () => {
        // Arrange
        const cohorts = [{ cohortID: 'cohort-1', cohortName: 'Test Cohort' }];
        vi.mocked(api.get).mockResolvedValue({ data: cohorts });

        // Act
        const result = await getCohorts();

        // Assert
        expect(api.get).toHaveBeenCalledWith('/admin/cohorts');
        expect(result).toEqual(cohorts);
    });

    /**
     * Sends the new status and gives back the updated cohort (normal)
     */
    it('saves whether a cohort is active', async () => {
        // Arrange
        const cohort = { cohortID: 'cohort-1', isActive: false };
        vi.mocked(api.put).mockResolvedValue({ data: cohort });

        // Act
        const result = await setCohortActive('cohort-1', false);

        // Assert
        expect(api.put).toHaveBeenCalledWith('/admin/cohorts/cohort-1/status', { isActive: false });
        expect(result).toEqual(cohort);
    });

    /**
     * Odd characters in an ID can't change which address gets called (edge)
     */
    it('makes IDs safe to put in an address', async () => {
        // Arrange
        vi.mocked(api.put).mockResolvedValue({ data: {} });

        // Act
        await setCohortActive('../students', true);

        // Assert
        expect(api.put).toHaveBeenCalledWith('/admin/cohorts/..%2Fstudents/status', { isActive: true });
    });

    /**
     * A refused save gets passed on so the page can show PHP's message (error)
     */
    it('passes a refused save on to the page', async () => {
        // Arrange
        const refused = { response: { status: 404, data: { error: 'Cohort not found' } } };
        vi.mocked(api.put).mockRejectedValue(refused);

        // Act
        const request = setCohortActive('cohort-1', false);

        // Assert
        await expect(request).rejects.toBe(refused);
    });
});
