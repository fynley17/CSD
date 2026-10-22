import { beforeEach, describe, expect, it, vi } from 'vitest';
import api from '../services/api';
import { getAvailableTests } from '../services/testService';

vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
    },
}));

describe('testService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('requests the tests endpoint and returns its response data', async () => {
        const tests = [{ testID: 'test-1', testName: 'Web Apps Fundamentals' }];
        api.get.mockResolvedValue({ data: tests });

        await expect(getAvailableTests()).resolves.toEqual(tests);
        expect(api.get).toHaveBeenCalledWith('/tests');
    });
});
