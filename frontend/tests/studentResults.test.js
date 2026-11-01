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
        // Arrange: beforeEach sets the summary from the server

        // Act
        const wrapper = mount(StudentResults);
        const cardsWhileLoading = cardText(wrapper);
        await flushPromises();

        // Assert
        expect(cardsWhileLoading).toEqual(['–', '–', '–']);
        expect(cardText(wrapper)).toEqual(['800', '44.2%', '11']);
        expect(wrapper.text()).toContain('Based on 11 tests');
    });

    it('shows the logged in student instead of placeholder details', async () => {
        // Arrange: beforeEach sets the logged in student

        // Act
        const wrapper = mount(StudentResults);
        await flushPromises();

        // Assert
        expect(wrapper.text()).toContain('Student: Ada Lovelace');
        expect(wrapper.text()).toContain('Student Number: WS000001');
        expect(wrapper.text()).not.toContain('John Doe');
    });

    it('capitalises each part of the name, including after a hyphen', async () => {
        // Arrange
        getResultsSummary.mockResolvedValue({
            studentName: 'mary-jane mcDonald smith-jones',
            studentNumber: 'ws000001',
            totalPoints: 0,
            averageScore: null,
            testsCompleted: 0,
        });

        // Act
        const wrapper = mount(StudentResults);
        await flushPromises();

        // Assert
        expect(wrapper.text()).toContain('Student: Mary-Jane McDonald Smith-Jones');
    });

    it('graphs the grade for each test from the oldest', async () => {
        // Arrange
        const completed = (completedID, completedDate, percentageCorrect) => ({
            completedID,
            testName: `Test ${completedID}`,
            testDescription: 'Review',
            subjectName: 'Maths',
            completedDate,
            questionCount: 10,
            pointsEarned: 30,
            percentageCorrect,
        });
        // the API sends the newest first
        getCompletedTests.mockResolvedValue([
            completed(3, '2026-10-20 09:00:00', 80),
            completed(2, '2026-10-10 09:00:00', 45.5),
            completed(1, '2026-10-01 09:00:00', 60),
        ]);

        // Act
        const wrapper = mount(StudentResults);
        await flushPromises();
        const { data } = wrapper.findComponent({ name: 'Line' }).props();

        // Assert
        expect(data.datasets[0].data.map((point) => point.y)).toEqual([60, 45.5, 80]);
    });

    it('pages the completed tests but still graphs all of them', async () => {
        // Arrange
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

        // Act
        const wrapper = mount(StudentResults);
        await flushPromises();

        // Assert
        expect(wrapper.findAll('.completedTestItem')).toHaveLength(5);
        expect(wrapper.findComponent({ name: 'Line' }).props().data.datasets[0].data).toHaveLength(7);
    });

    it('hides the graph when the completed tests fail to load', async () => {
        // Arrange
        getCompletedTests.mockRejectedValue(new Error('Network error'));

        // Act
        const wrapper = mount(StudentResults);
        await flushPromises();

        // Assert
        expect(wrapper.text()).not.toContain('Grades over time');
    });

    it('shows N/A for the average before any test is completed', async () => {
        // Arrange
        getResultsSummary.mockResolvedValue({
            studentName: 'Ada Lovelace',
            studentNumber: 'ws000001',
            totalPoints: 0,
            averageScore: null,
            testsCompleted: 0,
        });

        // Act
        const wrapper = mount(StudentResults);
        await flushPromises();

        // Assert
        expect(cardText(wrapper)).toEqual(['0', 'N/A', '0']);
        expect(wrapper.text()).toContain('No tests completed yet');
    });

    it('keeps the dashes and the completed tests when the summary fails to load', async () => {
        // Arrange
        getResultsSummary.mockRejectedValue({ response: { data: { error: 'Summary unavailable' } } });

        // Act
        const wrapper = mount(StudentResults);
        await flushPromises();

        // Assert
        expect(wrapper.find('[role="alert"]').text()).toBe('Summary unavailable');
        expect(cardText(wrapper)).toEqual(['–', '–', '–']);
        expect(wrapper.text()).toContain('You have not completed any tests yet.');
    });

    it('lists the completed tests once they have loaded', async () => {
        // Arrange
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

        // Act
        const wrapper = mount(StudentResults);
        const textWhileLoading = wrapper.text();
        await flushPromises();

        // Assert
        expect(textWhileLoading).toContain('Loading completed tests...');
        expect(wrapper.find('.completedTestList').text()).toContain('Algebra Basics');
        expect(wrapper.text()).not.toContain('Loading completed tests...');
    });

    it('shows a message when no tests have been completed', async () => {
        // Arrange
        getCompletedTests.mockResolvedValue([]);

        // Act
        const wrapper = mount(StudentResults);
        await flushPromises();

        // Assert
        expect(wrapper.text()).toContain('You have not completed any tests yet.');
    });

    it('shows the error from the server when the list fails to load', async () => {
        // Arrange
        getCompletedTests.mockRejectedValue({ response: { data: { error: 'Results unavailable' } } });

        // Act
        const wrapper = mount(StudentResults);
        await flushPromises();

        // Assert
        expect(wrapper.find('[role="alert"]').text()).toBe('Results unavailable');
    });
});
