// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import StudentResults from '../views/studentResults.vue';
import { getCompletedTests, getResultsSummary } from '../services/resultService';

vi.mock('../services/resultService', () => ({
    getCompletedTests: vi.fn(),
    getResultsSummary: vi.fn(),
    getCompletedTestReview: vi.fn(),
}));

// jsdom has no canvas to draw the graph on
vi.mock('vue-chartjs', () => ({
    Line: { name: 'Line', props: ['data'], template: '<canvas />' },
}));

describe('studentResults', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        getCompletedTests.mockResolvedValue([]);
        getResultsSummary.mockResolvedValue({
            studentName: 'Ada Lovelace',
            studentNumber: 'ws000001',
            totalPoints: 800,
            averageScore: 44.2,
            testsCompleted: 11,
        });
    });

    function cardText(wrapper) {
        return wrapper.findAll('.statValue').map((value) => value.text());
    }

    it('fills the cards with the totals from the server', async () => {
        const wrapper = mount(StudentResults);
        expect(cardText(wrapper)).toEqual(['–', '–', '–']);

        await flushPromises();

        expect(cardText(wrapper)).toEqual(['800', '44.2%', '11']);
        expect(wrapper.text()).toContain('Based on 11 tests');
    });

    it('shows the logged in student instead of placeholder details', async () => {
        const wrapper = mount(StudentResults);
        await flushPromises();

        expect(wrapper.text()).toContain('Student: Ada Lovelace');
        expect(wrapper.text()).toContain('Student Number: WS000001');
        expect(wrapper.text()).not.toContain('John Doe');
    });

    it('capitalises each part of the name, including after a hyphen', async () => {
        getResultsSummary.mockResolvedValue({
            studentName: 'mary-jane mcDonald smith-jones',
            studentNumber: 'ws000001',
            totalPoints: 0,
            averageScore: null,
            testsCompleted: 0,
        });

        const wrapper = mount(StudentResults);
        await flushPromises();

        expect(wrapper.text()).toContain('Student: Mary-Jane McDonald Smith-Jones');
    });

    it('adds the points up from the oldest test for the graph', async () => {
        const completed = (completedID, completedDate, pointsEarned) => ({
            completedID,
            testName: `Test ${completedID}`,
            testDescription: 'Review',
            subjectName: 'Maths',
            completedDate,
            questionCount: 10,
            pointsEarned,
            percentageCorrect: 50,
        });
        // the API sends the newest first
        getCompletedTests.mockResolvedValue([
            completed(3, '2026-10-20 09:00:00', 30),
            completed(2, '2026-10-10 09:00:00', -10),
            completed(1, '2026-10-01 09:00:00', 60),
        ]);

        const wrapper = mount(StudentResults);
        await flushPromises();
        const { data } = wrapper.findComponent({ name: 'Line' }).props();

        expect(data.datasets[0].data.map((point) => point.y)).toEqual([60, 50, 80]);
    });

    it('pages the completed tests but still graphs all of them', async () => {
        const tests = Array.from({ length: 7 }, (_, index) => ({
            completedID: index,
            testName: `Test ${index}`,
            testDescription: 'Review',
            subjectName: 'Maths',
            completedDate: '2026-10-20 09:00:00',
            questionCount: 10,
            pointsEarned: 30,
            percentageCorrect: 50,
        }));
        getCompletedTests.mockResolvedValue(tests);

        const wrapper = mount(StudentResults);
        await flushPromises();

        expect(wrapper.findAll('.completedTestItem')).toHaveLength(5);
        expect(wrapper.findComponent({ name: 'Line' }).props().data.datasets[0].data).toHaveLength(7);
    });

    it('hides the graph when the completed tests fail to load', async () => {
        getCompletedTests.mockRejectedValue(new Error('Network error'));

        const wrapper = mount(StudentResults);
        await flushPromises();

        expect(wrapper.text()).not.toContain('Points over time');
    });

    it('shows N/A for the average before any test is completed', async () => {
        getResultsSummary.mockResolvedValue({
            studentName: 'Ada Lovelace',
            studentNumber: 'ws000001',
            totalPoints: 0,
            averageScore: null,
            testsCompleted: 0,
        });

        const wrapper = mount(StudentResults);
        await flushPromises();

        expect(cardText(wrapper)).toEqual(['0', 'N/A', '0']);
        expect(wrapper.text()).toContain('No tests completed yet');
    });

    it('keeps the dashes and the completed tests when the summary fails to load', async () => {
        getResultsSummary.mockRejectedValue({ response: { data: { error: 'Summary unavailable' } } });

        const wrapper = mount(StudentResults);
        await flushPromises();

        expect(wrapper.find('[role="alert"]').text()).toBe('Summary unavailable');
        expect(cardText(wrapper)).toEqual(['–', '–', '–']);
        expect(wrapper.text()).toContain('You have not completed any tests yet.');
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
