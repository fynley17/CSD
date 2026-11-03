// Tests for subjectService.js
// laid out Arrange, Act, Assert
import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { createSubject, getSubjects, setSubjectActive, updateSubject } from '../services/subjectService';

// pretend api.js, no PHP needed
vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        patch: vi.fn(),
    },
}));

describe('subjectService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    /**
     * Calls /subjects and gives back the data (normal)
     */
    it('gets the subjects from the subjects address', async () => {
        // Arrange
        const subjects = [
            {
                subjectName: 'Test Databases',
                cohortName: 'Test Cohort',
                testCount: 2,
                lecturerNames: 'Sample Lecturer',
                isActive: true,
            },
        ];
        vi.mocked(api.get).mockResolvedValue({ data: subjects });

        // Act
        const result = await getSubjects();

        // Assert
        expect(api.get).toHaveBeenCalledWith('/subjects');
        expect(result).toEqual(subjects);
    });

    /**
     * A failed request gets passed on so the table can show the problem box (error)
     */
    it('passes a failed request on to the page', async () => {
        // Arrange
        const refused = { response: { status: 403, data: { error: 'You do not have permission to view this page' } } };
        vi.mocked(api.get).mockRejectedValue(refused);

        // Act
        const request = getSubjects();

        // Assert
        await expect(request).rejects.toBe(refused);
    });

    /**
     * Create, edit and archive each use the right method and address (normal)
     */
    it('sends create, edit and archive to the right addresses', async () => {
        // Arrange
        vi.mocked(api.post).mockResolvedValue({ data: { message: 'Subject saved' } });
        vi.mocked(api.put).mockResolvedValue({ data: { message: 'Subject saved' } });
        vi.mocked(api.patch).mockResolvedValue({ data: { message: 'Subject updated' } });
        const details = { subjectName: 'Test Databases', cohortID: 'cohort-1', lecturerIDs: ['lecturer-1'] };

        // Act
        await createSubject(details);
        await updateSubject('subject-1', details);
        await setSubjectActive('subject-1', false);

        // Assert
        expect(api.post).toHaveBeenCalledWith('/subjects', details);
        expect(api.put).toHaveBeenCalledWith('/subjects/subject-1', details);
        expect(api.patch).toHaveBeenCalledWith('/subjects/subject-1/active', { isActive: false });
    });
});
