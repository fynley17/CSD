// Tests for cohortService.js
// laid out Arrange, Act, Assert
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import {
    assignSubjects,
    createCohort,
    deleteCohort,
    getCohorts,
    getUnassignedSubjects,
    removeSubject,
    setCohortActive,
    updateCohort,
} from '../services/cohortService';

// pretend api.js, no PHP needed
vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        put: vi.fn(),
        post: vi.fn(),
        delete: vi.fn(),
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
     * Calls the unassigned address and gives back the data (normal)
     */
    it('gets the unassigned subjects', async () => {
        // Arrange
        const subjects = [{ subjectID: 'subject-1', subjectName: 'Test Databases', testCount: 2 }];
        vi.mocked(api.get).mockResolvedValue({ data: subjects });

        // Act
        const result = await getUnassignedSubjects();

        // Assert
        expect(api.get).toHaveBeenCalledWith('/admin/subjects/unassigned');
        expect(result).toEqual(subjects);
    });

    /**
     * Sends the chosen subjects and gives back the updated cohort (normal)
     */
    it('assigns subjects to a cohort', async () => {
        // Arrange
        const cohort = { cohortID: 'cohort-1', subjects: [] };
        vi.mocked(api.post).mockResolvedValue({ data: cohort });

        // Act
        const result = await assignSubjects('cohort-1', ['subject-1', 'subject-2']);

        // Assert
        expect(api.post).toHaveBeenCalledWith('/admin/cohorts/cohort-1/subjects', {
            subjectIDs: ['subject-1', 'subject-2'],
        });
        expect(result).toEqual(cohort);
    });

    /**
     * Calls the address for that cohort's subject and gives back the updated cohort (normal)
     */
    it('removes a subject from a cohort', async () => {
        // Arrange
        const cohort = { cohortID: 'cohort-1', subjects: [] };
        vi.mocked(api.delete).mockResolvedValue({ data: cohort });

        // Act
        const result = await removeSubject('cohort-1', 'subject-1');

        // Assert
        expect(api.delete).toHaveBeenCalledWith('/admin/cohorts/cohort-1/subjects/subject-1');
        expect(result).toEqual(cohort);
    });

    /**
     * Calls the address for that cohort (normal)
     */
    it('deletes a cohort', async () => {
        // Arrange
        vi.mocked(api.delete).mockResolvedValue({ data: { message: 'Cohort deleted' } });

        // Act
        const result = await deleteCohort('cohort-1');

        // Assert
        expect(api.delete).toHaveBeenCalledWith('/admin/cohorts/cohort-1');
        expect(result).toEqual({ message: 'Cohort deleted' });
    });

    /**
     * Sends the name and gives back the new cohort (normal)
     */
    it('creates a cohort', async () => {
        // Arrange
        const cohort = { cohortID: 'cohort-1', cohortName: 'Test Cohort' };
        vi.mocked(api.post).mockResolvedValue({ data: cohort });

        // Act
        const result = await createCohort('Test Cohort', true);

        // Assert
        expect(api.post).toHaveBeenCalledWith('/admin/cohorts', { cohortName: 'Test Cohort', isActive: true });
        expect(result).toEqual(cohort);
    });

    /**
     * Sends the name and status to that cohort's address (normal)
     */
    it('saves a cohort name and status', async () => {
        // Arrange
        const cohort = { cohortID: 'cohort-1', cohortName: 'New Name' };
        vi.mocked(api.put).mockResolvedValue({ data: cohort });

        // Act
        const result = await updateCohort('cohort-1', 'New Name', false);

        // Assert
        expect(api.put).toHaveBeenCalledWith('/admin/cohorts/cohort-1', { cohortName: 'New Name', isActive: false });
        expect(result).toEqual(cohort);
    });

    /**
     * Odd characters in an ID can't change which address gets called (edge)
     */
    it('makes IDs safe to put in an address', async () => {
        // Arrange
        vi.mocked(api.delete).mockResolvedValue({ data: {} });

        // Act
        await deleteCohort('../students');
        await removeSubject('a/b', 'c?d');

        // Assert
        expect(api.delete).toHaveBeenCalledWith('/admin/cohorts/..%2Fstudents');
        expect(api.delete).toHaveBeenCalledWith('/admin/cohorts/a%2Fb/subjects/c%3Fd');
    });

    /**
     * A refused delete gets passed on so the pop-up can show PHP's message (error)
     */
    it('passes a refused delete on to the page', async () => {
        // Arrange
        const refused = { response: { status: 409, data: { error: 'This cohort still has students' } } };
        vi.mocked(api.delete).mockRejectedValue(refused);

        // Act
        const request = deleteCohort('cohort-1');

        // Assert
        await expect(request).rejects.toBe(refused);
    });

    /**
     * Odd characters in the ID can't change which cohort gets saved (edge)
     */
    it('makes the ID safe when saving a cohort', async () => {
        // Arrange
        vi.mocked(api.put).mockResolvedValue({ data: {} });

        // Act
        await updateCohort('../students', 'New Name', true);

        // Assert
        expect(api.put).toHaveBeenCalledWith('/admin/cohorts/..%2Fstudents', {
            cohortName: 'New Name',
            isActive: true,
        });
    });

    /**
     * A duplicate name gets passed on so the pop-up can show PHP's message (error)
     */
    it('passes a refused name on to the page', async () => {
        // Arrange
        const refused = { response: { status: 400, data: { error: 'A cohort with this name already exists' } } };
        vi.mocked(api.post).mockRejectedValue(refused);

        // Act
        const request = createCohort('Test Cohort', true);

        // Assert
        await expect(request).rejects.toBe(refused);
    });
});
