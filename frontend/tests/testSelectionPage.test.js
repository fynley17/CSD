// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TestSelectionPage from '../views/testSelectionPage.vue';
import { getAvailableTests } from '../services/testService';

vi.mock('../services/testService', () => ({
    getAvailableTests: vi.fn(),
}));

const push = vi.fn();
vi.mock('vue-router', () => ({
    useRouter: () => ({ push }),
}));

const test = {
    testID: 'test-1',
    testName: 'Web Apps Fundamentals',
    testDescription: 'Review core web application concepts.',
    questionCount: 15,
    subjectName: 'Web Apps',
    createdByName: 'Alex Lecturer',
    timeLimit: null,
    dueDate: null,
};

describe('TestSelectionPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('shows loading state and then renders cards for the returned tests', async () => {
        let resolveTests;
        getAvailableTests.mockReturnValue(
            new Promise((resolve) => {
                resolveTests = resolve;
            }),
        );

        const wrapper = mount(TestSelectionPage);
        expect(wrapper.get('[role="status"]').text()).toBe('Loading tests...');
        expect(getAvailableTests).toHaveBeenCalledOnce();

        resolveTests([test]);
        await flushPromises();

        expect(wrapper.findAll('.testCard')).toHaveLength(1);
        expect(wrapper.get('.testCardTitle').text()).toBe(test.testName);
    });

    it('shows an empty state when no tests are available', async () => {
        getAvailableTests.mockResolvedValue([]);

        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        expect(wrapper.text()).toContain('There are no tests available right now.');
        expect(wrapper.find('.testCard').exists()).toBe(false);
    });

    it('shows the API error when loading tests fails', async () => {
        getAvailableTests.mockRejectedValue({
            response: { data: { error: 'Unable to load tests.' } },
        });

        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('Unable to load tests.');
    });

    it('opens the testing page for the test whose Start button was clicked', async () => {
        getAvailableTests.mockResolvedValue([test]);

        const wrapper = mount(TestSelectionPage);
        await flushPromises();
        await wrapper.get('.testCardStartButton').trigger('click');

        expect(push).toHaveBeenCalledWith({ name: 'testing', params: { testID: 'test-1' } });
    });
});
