// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import StudentResults from '../views/studentResults.vue';
import { getCompletedTests } from '../services/resultService';

vi.mock('../services/resultService', () => ({
    getCompletedTests: vi.fn(),
    getCompletedTestReview: vi.fn(),
}));

// jsdom has no canvas to draw the graph on
vi.mock('vue-chartjs', () => ({
    Line: { template: '<canvas />' },
}));

describe('studentResults', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('lists the completed tests once they have loaded', async () => {
        getCompletedTests.mockResolvedValue([
            {
                completedID: 1,
                testName: 'Algebra Basics',
                testDescription: 'Linear equations',
                subjectName: 'Maths',
                completedDate: '2026-10-28',
                questionCount: 10,
                pointsEarned: 9,
                percentageCorrect: 90,
            },
        ]);

        const wrapper = mount(StudentResults);
        expect(wrapper.text()).toContain('Loading completed tests...');

        await flushPromises();

        expect(wrapper.find('.completedTestList').text()).toContain('Algebra Basics');
        expect(wrapper.text()).not.toContain('Loading completed tests...');
    });

    it('shows a message when no tests have been completed', async () => {
        getCompletedTests.mockResolvedValue([]);

        const wrapper = mount(StudentResults);
        await flushPromises();

        expect(wrapper.text()).toContain('You have not completed any tests yet.');
    });

    it('shows the error from the server when the list fails to load', async () => {
        getCompletedTests.mockRejectedValue({ response: { data: { error: 'Results unavailable' } } });

        const wrapper = mount(StudentResults);
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toBe('Results unavailable');
    });
});
