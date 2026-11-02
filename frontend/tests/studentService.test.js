import { describe, it, expect, vi, beforeEach } from 'vitest';
import api from '../services/api';
import { getLecturerStudents } from '../services/studentService';

vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
    },
}));

describe('getLecturerStudents', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('returns the student data from the API', async () => {
        // Arrange
        const mockStudents = [
            {
                userID: 1,
                name: 'Alex Morgan',
                studentNumber: 'ws000001',
                status: 'Active',
            },
            {
                userID: 2,
                name: 'Jamie Wilson',
                studentNumber: 'ws000002',
                status: 'Pending',
            },
        ];

        api.get.mockResolvedValue({
            data: mockStudents,
        });

        // Act
        const result = await getLecturerStudents();

        // Assert
        expect(api.get).toHaveBeenCalledWith('/students');
        expect(result).toEqual(mockStudents);
    });

    it('throws an error when the API request fails', async () => {
        // Arrange
        const mockError = new Error('API request failed');

        api.get.mockRejectedValue(mockError);

        // Act & Assert
        await expect(getLecturerStudents()).rejects.toThrow('API request failed');
    });
});
