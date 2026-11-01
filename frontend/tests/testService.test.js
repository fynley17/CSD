import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { getAvailableTests, startTestAttempt, submitTest } from '../services/testService';

vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
    },
}));

describe('testService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('requests the tests endpoint and returns its response data', async () => {
        // Arrange
        const tests = [{ testID: 'test-1', testName: 'Web Apps Fundamentals' }];
        api.get.mockResolvedValue({ data: tests });

        // Act
        const response = await getAvailableTests();

        // Assert
        expect(response).toEqual(tests);
        expect(api.get).toHaveBeenCalledWith('/tests');
    });

    it('starts an attempt at one test by its ID', async () => {
        // Arrange
        const test = { testID: 'test-1', questions: [] };
        api.post.mockResolvedValue({ data: test });

        // Act
        const response = await startTestAttempt('test-1');

        // Assert
        expect(response).toEqual(test);
        expect(api.post).toHaveBeenCalledWith('/tests/test-1/start');
    });

    it('posts the chosen answers and returns the marked test', async () => {
        // Arrange
        const result = { correctCount: 1, questions: [] };
        api.post.mockResolvedValue({ data: result });

        // Act
        const response = await submitTest('test-1', { 'question-1': 'answer-1' });

        // Assert
        expect(response).toEqual(result);
        expect(api.post).toHaveBeenCalledWith('/tests/test-1/complete', {
            answers: { 'question-1': 'answer-1' },
        });
    });
});
