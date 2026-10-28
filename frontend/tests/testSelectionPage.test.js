// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TestSelectionPage from '../views/testSelectionPage.vue';
import { getAvailableTests } from '../services/testService';
import { getCompletedTests } from '../services/resultService';

vi.mock('../services/testService', () => ({
    getAvailableTests: vi.fn(),
}));

const push = vi.fn();
vi.mock('vue-router', () => ({
    useRouter: () => ({ push }),
}));

vi.mock('../services/resultService', () => ({
    getCompletedTests: vi.fn(),
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

const completedTest = {
    completedID: 'completed-1',
    testID: 'test-2',
    testName: 'Cyber Security Basics',
    testDescription: 'Check your understanding of security basics.',
    subjectName: 'Cyber Security',
    completedDate: '2026-10-20 09:00:00',
    questionCount: 10,
    pointsEarned: 260,
    percentageCorrect: 80,
};

describe('TestSelectionPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        getCompletedTests.mockResolvedValue([]);
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

    it('deals the cards across three columns so each stacks under the one above it', async () => {
        getAvailableTests.mockResolvedValue(
            ['a', 'b', 'c', 'd'].map((id) => ({ ...test, testID: id, testName: `Test ${id}` })),
        );

        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        const columns = wrapper
            .findAll('.testCardColumn')
            .map((column) => column.findAll('.testCardTitle').map((title) => title.text()));
        expect(columns).toEqual([['Test a', 'Test d'], ['Test b'], ['Test c']]);
    });

    it('opens the testing page for the test whose Start button was clicked', async () => {
        getAvailableTests.mockResolvedValue([test]);

        const wrapper = mount(TestSelectionPage);
        await flushPromises();
        await wrapper.get('.testCardStartButton').trigger('click');

        expect(push).toHaveBeenCalledWith({ name: 'testing', params: { testID: 'test-1' } });
    });

    it('renders the completed tests below the tests to complete', async () => {
        getAvailableTests.mockResolvedValue([test]);
        getCompletedTests.mockResolvedValue([completedTest]);

        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        expect(getCompletedTests).toHaveBeenCalledOnce();
        expect(wrapper.findAll('.completedTestItem')).toHaveLength(1);
        expect(wrapper.get('.completedTestTitle').text()).toBe(completedTest.testName);
        expect(wrapper.get('.completedTestPercentage').text()).toBe('80%');
    });

    it('shows an empty state when no tests have been completed', async () => {
        getAvailableTests.mockResolvedValue([test]);

        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        expect(wrapper.text()).toContain('You have not completed any tests yet.');
        expect(wrapper.find('.completedTestItem').exists()).toBe(false);
    });

    it('still shows the tests to complete when completed tests fail to load', async () => {
        getAvailableTests.mockResolvedValue([test]);
        getCompletedTests.mockRejectedValue(new Error('Network error'));

        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        expect(wrapper.findAll('.testCard')).toHaveLength(1);
        expect(wrapper.get('[role="alert"]').text()).toBe('Unable to load completed tests. Please try again later.');
    });
});
