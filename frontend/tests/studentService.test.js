import { describe, it, expect, vi, beforeEach } from 'vitest';
import api from '../services/api';
import {
    getLecturerStudents,
    createStudent,
    updateStudentStatus,
    approveStudent,
    declineStudent,
    updateStudent,
    deleteStudent,
} from '../services/studentService';

vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('Student Service', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    describe('getLecturerStudents', () => {
        it('returns student data from the API', async () => {
            const mockStudents = [
                {
                    userID: 'student-1',
                    name: 'Alex Morgan',
                    studentNumber: 'ws000001',
                    status: 'Active',
                },
                {
                    userID: 'student-2',
                    name: 'Jamie Wilson',
                    studentNumber: 'ws000002',
                    status: 'Pending',
                },
            ];

            api.get.mockResolvedValue({ data: mockStudents });

            const result = await getLecturerStudents();

            expect(api.get).toHaveBeenCalledWith('/students');
            expect(result).toEqual(mockStudents);
        });

        it('throws an error when the API request fails', async () => {
            api.get.mockRejectedValue(new Error('API request failed'));

            await expect(getLecturerStudents()).rejects.toThrow('API request failed');
        });
    });

    describe('createStudent', () => {
        it('creates a student using the supplied details', async () => {
            const studentData = {
                firstName: 'Alex',
                lastName: 'Morgan',
                studentNumber: 'ws000001',
                email: 'alex@example.com',
                cohortID: 'cohort-1',
            };

            const mockResponse = { message: 'Student created successfully' };
            api.post.mockResolvedValue({ data: mockResponse });

            const result = await createStudent(studentData);

            expect(api.post).toHaveBeenCalledWith('/students', studentData);
            expect(result).toEqual(mockResponse);
        });

        it('throws an error when student creation fails', async () => {
            api.post.mockRejectedValue(new Error('Creation failed'));

            await expect(createStudent({})).rejects.toThrow('Creation failed');
        });
    });

    describe('updateStudentStatus', () => {
        it('updates a student account status', async () => {
            const userID = 'student-1';
            const isEnabled = false;
            const mockResponse = { message: 'Student account disabled successfully.' };

            api.put.mockResolvedValue({ data: mockResponse });

            const result = await updateStudentStatus(userID, isEnabled);

            expect(api.put).toHaveBeenCalledWith(`/students/${encodeURIComponent(userID)}/status`, { isEnabled });
            expect(result).toEqual(mockResponse);
        });
    });

    describe('approveStudent', () => {
        it('approves a pending student application', async () => {
            const userID = 'student-1';
            const mockResponse = { message: 'Application approved' };

            api.post.mockResolvedValue({ data: mockResponse });

            const result = await approveStudent(userID);

            expect(api.post).toHaveBeenCalledWith(`/students/${encodeURIComponent(userID)}/approve`);
            expect(result).toEqual(mockResponse);
        });
    });

    describe('declineStudent', () => {
        it('declines and removes a pending student application', async () => {
            const userID = 'student-2';
            const mockResponse = { message: 'Application declined' };

            api.delete.mockResolvedValue({ data: mockResponse });

            const result = await declineStudent(userID);

            expect(api.delete).toHaveBeenCalledWith(`/students/${encodeURIComponent(userID)}/application`);
            expect(result).toEqual(mockResponse);
        });
    });

    describe('updateStudent', () => {
        it('updates the supplied student details', async () => {
            const userID = 'student-1';
            const studentData = {
                firstName: 'Alex',
                lastName: 'Smith',
                email: 'alex.smith@example.com',
                studentNumber: 'ws000001',
                cohortID: 'cohort-2',
            };

            const mockResponse = { message: 'Student updated successfully' };
            api.put.mockResolvedValue({ data: mockResponse });

            const result = await updateStudent(userID, studentData);

            expect(api.put).toHaveBeenCalledWith(`/students/${encodeURIComponent(userID)}`, studentData);
            expect(result).toEqual(mockResponse);
        });
    });

    describe('deleteStudent', () => {
        it('deletes a student using their user ID', async () => {
            const userID = 'student-1';
            const mockResponse = { message: 'Student deleted successfully' };

            api.delete.mockResolvedValue({ data: mockResponse });

            const result = await deleteStudent(userID);

            expect(api.delete).toHaveBeenCalledWith(`/students/${encodeURIComponent(userID)}`);
            expect(result).toEqual(mockResponse);
        });
    });
});
