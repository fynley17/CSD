import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import {
    getAvailableTests,
    startTestAttempt,
    submitTest,
    getLecturerTests,
    getActiveTestSubjects,
    createTest,
    updateTest,
    deleteTest,
} from '../services/testService';

vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('testService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    // Existing student test functionality.
    it('requests the tests endpoint and returns its response data', async () => {
        const tests = [{ testID: 'test-1', testName: 'Web Apps Fundamentals' }];
        api.get.mockResolvedValue({ data: tests });

        const response = await getAvailableTests();

        expect(response).toEqual(tests);
        expect(api.get).toHaveBeenCalledWith('/tests');
    });

    it('starts an attempt at one test by its ID', async () => {
        const test = { testID: 'test-1', questions: [] };
        api.post.mockResolvedValue({ data: test });

        const response = await startTestAttempt('test-1');

        expect(response).toEqual(test);
        expect(api.post).toHaveBeenCalledWith('/tests/test-1/start');
    });

    it('posts the chosen answers and returns the marked test', async () => {
        const result = { correctCount: 1, questions: [] };
        api.post.mockResolvedValue({ data: result });

        const response = await submitTest('test-1', {
            'question-1': 'answer-1',
        });

        expect(response).toEqual(result);
        expect(api.post).toHaveBeenCalledWith('/tests/test-1/complete', {
            answers: { 'question-1': 'answer-1' },
        });
    });

    // Lecturer test management functionality.
    it('retrieves tests for lecturer management', async () => {
        const tests = [{ testID: 'test-1', testName: 'AI Tests' }];
        api.get.mockResolvedValue({ data: tests });

        const response = await getLecturerTests();

        expect(response).toEqual(tests);
        expect(api.get).toHaveBeenCalledWith('/lecturer/tests');
    });

    it('retrieves active subjects for test creation', async () => {
        const subjects = [{ subjectID: 'subject-1', subjectName: 'Mathematics' }];
        api.get.mockResolvedValue({ data: subjects });

        const response = await getActiveTestSubjects();

        expect(response).toEqual(subjects);
        expect(api.get).toHaveBeenCalledWith('/lecturer/test-subjects');
    });

    it('creates a new test', async () => {
        const testData = {
            testName: 'AI Tests',
            subjectID: 'subject-1',
            testDescription: 'Artificial intelligence assessment',
            timeLimit: 60,
            dueDate: null,
            questionCount: 10,
            questionSelectionMethod: 'random',
        };

        const result = {
            message: 'Test created successfully.',
            testID: 'test-1',
        };
        api.post.mockResolvedValue({ data: result });

        const response = await createTest(testData);

        expect(response).toEqual(result);
        expect(api.post).toHaveBeenCalledWith('/lecturer/tests', testData);
    });

    it('updates an existing test', async () => {
        const testData = {
            testName: 'Updated AI Tests',
            subjectID: 'subject-1',
            testDescription: 'Updated assessment',
            timeLimit: 90,
            dueDate: null,
            questionCount: 12,
            questionSelectionMethod: 'frequently_incorrect',
        };

        const result = { message: 'Test updated successfully.' };
        api.put.mockResolvedValue({ data: result });

        const response = await updateTest('test-1', testData);

        expect(response).toEqual(result);
        expect(api.put).toHaveBeenCalledWith('/lecturer/tests/test-1', testData);
    });

    it('deletes an existing test', async () => {
        const result = { message: 'Test deleted successfully.' };
        api.delete.mockResolvedValue({ data: result });

        const response = await deleteTest('test-1');

        expect(response).toEqual(result);
        expect(api.delete).toHaveBeenCalledWith('/lecturer/tests/test-1');
    });
});
