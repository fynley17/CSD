// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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
        // Arrange
        let resolveTests;
        getAvailableTests.mockReturnValue(
            new Promise((resolve) => {
                resolveTests = resolve;
            }),
        );

        // Act
        const wrapper = mount(TestSelectionPage);
        const textWhileLoading = wrapper.get('[role="status"]').text();

        resolveTests([test]);
        await flushPromises();

        // Assert
        expect(textWhileLoading).toBe('Loading tests...');
        expect(getAvailableTests).toHaveBeenCalledOnce();
        expect(wrapper.findAll('.testCard')).toHaveLength(1);
        expect(wrapper.get('.testCardTitle').text()).toBe(test.testName);
    });

    it('shows an empty state when no tests are available', async () => {
        // Arrange
        getAvailableTests.mockResolvedValue([]);

        // Act
        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        // Assert
        expect(wrapper.text()).toContain('There are no tests available right now.');
        expect(wrapper.find('.testCard').exists()).toBe(false);
    });

    it('shows the API error when loading tests fails', async () => {
        // Arrange
        getAvailableTests.mockRejectedValue({
            response: { data: { error: 'Unable to load tests.' } },
        });

        // Act
        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toBe('Unable to load tests.');
    });

    it('lays the cards out in date order in a single grid', async () => {
        // Arrange
        getAvailableTests.mockResolvedValue(
            ['a', 'b', 'c', 'd'].map((id) => ({ ...test, testID: id, testName: `Test ${id}` })),
        );

        // Act
        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        // Assert
        const titles = wrapper
            .get('.testCardGrid')
            .findAll('.testCardTitle')
            .map((title) => title.text());
        expect(titles).toEqual(['Test a', 'Test b', 'Test c', 'Test d']);
    });

    it('opens the testing page for the test whose Start button was clicked', async () => {
        // Arrange
        getAvailableTests.mockResolvedValue([test]);

        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        // Act
        await wrapper.get('.testCardStartButton').trigger('click');

        // Assert
        expect(push).toHaveBeenCalledWith({ name: 'testing', params: { testID: 'test-1' } });
    });

    it('renders the completed tests below the tests to complete', async () => {
        // Arrange
        getAvailableTests.mockResolvedValue([test]);
        getCompletedTests.mockResolvedValue([completedTest]);

        // Act
        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        // Assert
        expect(getCompletedTests).toHaveBeenCalledOnce();
        expect(wrapper.findAll('.completedTestItem')).toHaveLength(1);
        expect(wrapper.get('.completedTestTitle').text()).toBe(completedTest.testName);
        expect(wrapper.get('.completedTestPercentage').text()).toBe('80%');
    });

    it('pages the completed tests, 5 at a time', async () => {
        // Arrange
        getAvailableTests.mockResolvedValue([]);
        getCompletedTests.mockResolvedValue(
            Array.from({ length: 6 }, (_, index) => ({ ...completedTest, completedID: `completed-${index}` })),
        );

        // Act
        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        // Assert
        expect(wrapper.findAll('.completedTestItem')).toHaveLength(5);
        expect(wrapper.text()).toContain('Showing 1 to 5 of 6 tests');
    });

    it('shows an empty state when no tests have been completed', async () => {
        // Arrange
        getAvailableTests.mockResolvedValue([test]);

        // Act
        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        // Assert
        expect(wrapper.text()).toContain('You have not completed any tests yet.');
        expect(wrapper.find('.completedTestItem').exists()).toBe(false);
    });

    it('still shows the tests to complete when completed tests fail to load', async () => {
        // Arrange
        getAvailableTests.mockResolvedValue([test]);
        getCompletedTests.mockRejectedValue(new Error('Network error'));

        // Act
        const wrapper = mount(TestSelectionPage);
        await flushPromises();

        // Assert
        expect(wrapper.findAll('.testCard')).toHaveLength(1);
        expect(wrapper.get('[role="alert"]').text()).toBe('Unable to load completed tests. Please try again later.');
    });

    describe('tests to complete pages', () => {
        const manyTests = (count) =>
            Array.from({ length: count }, (_, index) => ({
                ...test,
                testID: `test-${index + 1}`,
                testName: `Test ${index + 1}`,
            }));

        // pretends the screen is a given width, listeners lets a test fire a resize
        let listeners;
        function setScreen(width) {
            listeners = [];
            window.matchMedia = vi.fn((query) => {
                const maxWidth = Number(query.match(/max-width: ([\d.]+)px/)[1]);
                return {
                    get matches() {
                        return currentWidth <= maxWidth;
                    },
                    addEventListener: (_, listener) => listeners.push(listener),
                    removeEventListener: vi.fn(),
                };
            });
            let currentWidth = width;
            return {
                resize(newWidth) {
                    currentWidth = newWidth;
                    listeners.forEach((listener) => listener());
                },
            };
        }

        const titles = (wrapper) => wrapper.findAll('.testCardTitle').map((title) => title.text());
        const pageButton = (wrapper, text) => wrapper.findAll('.page-link').find((link) => link.text() === text);

        afterEach(() => {
            delete window.matchMedia;
        });

        it('shows two rows of three on desktop, then pages the rest', async () => {
            // Arrange
            setScreen(1200);
            getAvailableTests.mockResolvedValue(manyTests(8));

            // Act
            const wrapper = mount(TestSelectionPage);
            await flushPromises();
            const firstPage = titles(wrapper);
            await pageButton(wrapper, 'Next').trigger('click');

            // Assert
            expect(firstPage).toEqual(['Test 1', 'Test 2', 'Test 3', 'Test 4', 'Test 5', 'Test 6']);
            expect(titles(wrapper)).toEqual(['Test 7', 'Test 8']);
            expect(wrapper.text()).toContain('Showing 7 to 8 of 8 tests');
        });

        it('has no pages when the tests fit in two rows', async () => {
            // Arrange
            setScreen(1200);
            getAvailableTests.mockResolvedValue(manyTests(6));

            // Act
            const wrapper = mount(TestSelectionPage);
            await flushPromises();

            // Assert
            expect(titles(wrapper)).toHaveLength(6);
            expect(wrapper.find('[aria-label="Tests to complete pages"]').exists()).toBe(false);
        });

        it.each([
            [800, 4],
            [400, 2],
        ])('shows two rows on a %ipx screen, %i cards', async (width, perPage) => {
            // Arrange
            setScreen(width);
            getAvailableTests.mockResolvedValue(manyTests(8));

            // Act
            const wrapper = mount(TestSelectionPage);
            await flushPromises();

            // Assert
            expect(titles(wrapper)).toHaveLength(perPage);
            expect(wrapper.text()).toContain(`Showing 1 to ${perPage} of 8 tests`);
        });

        it('keeps the first card on screen when the window is resized', async () => {
            // Arrange: desktop, on page 2, which starts at Test 7
            const screen = setScreen(1200);
            getAvailableTests.mockResolvedValue(manyTests(12));
            const wrapper = mount(TestSelectionPage);
            await flushPromises();
            await pageButton(wrapper, 'Next').trigger('click');

            // Act: down to a phone, 2 cards a page
            screen.resize(400);
            await flushPromises();

            // Assert: Test 7 is the first card on page 4 of the phone layout
            expect(titles(wrapper)).toEqual(['Test 7', 'Test 8']);
            expect(wrapper.find('[aria-current="page"]').text()).toBe('4');
        });
    });

    describe('Upcoming and Overdue tabs', () => {
        const tabTests = [
            { ...test, testID: 'up-1', testName: 'Up 1' },
            { ...test, testID: 'up-2', testName: 'Up 2' },
            { ...test, testID: 'late-1', testName: 'Late 1', isOverdue: true },
        ];
        const titles = (wrapper) => wrapper.findAll('.testCardTitle').map((title) => title.text());
        const tab = (wrapper, name) => wrapper.findAll('[role="tab"]').find((button) => button.text().startsWith(name));

        it('opens on Upcoming, with a count on each tab', async () => {
            // Arrange
            getAvailableTests.mockResolvedValue(tabTests);

            // Act
            const wrapper = mount(TestSelectionPage);
            await flushPromises();

            // Assert
            expect(tab(wrapper, 'Upcoming').attributes('aria-selected')).toBe('true');
            expect(tab(wrapper, 'Upcoming').text()).toBe('Upcoming 2');
            expect(tab(wrapper, 'Overdue').text()).toBe('Overdue 1');
            expect(titles(wrapper)).toEqual(['Up 1', 'Up 2']);
        });

        it('shows only overdue tests on the Overdue tab', async () => {
            // Arrange
            getAvailableTests.mockResolvedValue(tabTests);
            const wrapper = mount(TestSelectionPage);
            await flushPromises();

            // Act
            await tab(wrapper, 'Overdue').trigger('click');

            // Assert
            expect(tab(wrapper, 'Overdue').attributes('aria-selected')).toBe('true');
            expect(tab(wrapper, 'Upcoming').attributes('tabindex')).toBe('-1');
            expect(titles(wrapper)).toEqual(['Late 1']);
            expect(wrapper.get('[role="tabpanel"]').attributes('aria-labelledby')).toBe('overdueTab');
        });

        it('says so when a tab has no tests', async () => {
            // Arrange
            getAvailableTests.mockResolvedValue(tabTests.slice(0, 2));
            const wrapper = mount(TestSelectionPage);
            await flushPromises();

            // Act
            await tab(wrapper, 'Overdue').trigger('click');

            // Assert
            expect(wrapper.get('[role="tabpanel"]').text()).toBe('You have no overdue tests.');
        });

        it('starts each tab on its first page', async () => {
            // Arrange: 8 upcoming tests is two pages on desktop
            getAvailableTests.mockResolvedValue([
                ...Array.from({ length: 8 }, (_, index) => ({ ...test, testID: `u${index}`, testName: `U${index}` })),
                { ...test, testID: 'late', testName: 'Late', isOverdue: true },
            ]);
            const wrapper = mount(TestSelectionPage);
            await flushPromises();
            await wrapper
                .findAll('.page-link')
                .find((link) => link.text() === 'Next')
                .trigger('click');

            // Act
            await tab(wrapper, 'Overdue').trigger('click');
            await tab(wrapper, 'Upcoming').trigger('click');

            // Assert
            expect(titles(wrapper)[0]).toBe('U0');
        });

        it('moves between tabs with the arrow keys', async () => {
            // Arrange
            getAvailableTests.mockResolvedValue(tabTests);
            const wrapper = mount(TestSelectionPage, { attachTo: document.body });
            await flushPromises();

            // Act
            await tab(wrapper, 'Upcoming').trigger('keydown', { key: 'ArrowRight' });
            const afterRight = document.activeElement?.id;
            await tab(wrapper, 'Overdue').trigger('keydown', { key: 'Home' });

            // Assert
            expect(afterRight).toBe('overdueTab');
            expect(tab(wrapper, 'Upcoming').attributes('aria-selected')).toBe('true');
            expect(document.activeElement?.id).toBe('upcomingTab');
            wrapper.unmount();
        });
    });

    describe('search and subject filter', () => {
        const filterTests = [
            { ...test, testID: 'sql', testName: 'SQL Joins', subjectName: 'Databases', createdByName: 'Helen Carter' },
            { ...test, testID: 'vue', testName: 'Vue Components', subjectName: 'Web Apps', createdByName: 'Tom Reid' },
            {
                ...test,
                testID: 'er',
                testName: 'ER Diagrams',
                subjectName: 'Databases',
                createdByName: 'Tom Reid',
                isOverdue: true,
            },
        ];
        const titles = (wrapper) => wrapper.findAll('.testCardTitle').map((title) => title.text());
        const tabText = (wrapper) => wrapper.findAll('[role="tab"]').map((tab) => tab.text());

        async function mountPage(tests = filterTests) {
            getAvailableTests.mockResolvedValue(tests);
            const wrapper = mount(TestSelectionPage);
            await flushPromises();
            return wrapper;
        }

        it('lists the subjects the student has tests in, in order', async () => {
            // Act
            const wrapper = await mountPage();

            // Assert
            const options = wrapper.findAll('#testSelectionSubject option').map((option) => option.text());
            expect(options).toEqual(['All subjects', 'Databases', 'Web Apps']);
        });

        it('searches names, subjects and lecturers whatever the case, and updates the tab counts', async () => {
            // Arrange
            const wrapper = await mountPage();

            // Act
            await wrapper.get('#testSelectionSearch').setValue('  tom REID ');

            // Assert
            expect(titles(wrapper)).toEqual(['Vue Components']);
            expect(tabText(wrapper)).toEqual(['Upcoming 1', 'Overdue 1']);
        });

        it('filters both tabs by subject', async () => {
            // Arrange
            const wrapper = await mountPage();

            // Act
            await wrapper.get('#testSelectionSubject').setValue('Databases');

            // Assert
            expect(titles(wrapper)).toEqual(['SQL Joins']);
            expect(tabText(wrapper)).toEqual(['Upcoming 1', 'Overdue 1']);
        });

        it('says when nothing matches and clears the search and filter', async () => {
            // Arrange
            const wrapper = await mountPage();
            await wrapper.get('#testSelectionSearch').setValue('networking');

            // Act
            const message = wrapper.get('[role="tabpanel"]').text();
            await wrapper.get('.clearFilters').trigger('click');

            // Assert
            expect(message).toContain('No upcoming tests match your search.');
            expect(wrapper.get('#testSelectionSearch').element.value).toBe('');
            expect(titles(wrapper)).toEqual(['SQL Joins', 'Vue Components']);
        });

        it('goes back to the first page when the search changes', async () => {
            // Arrange: 8 tests is two pages on desktop
            const wrapper = await mountPage(
                Array.from({ length: 8 }, (_, index) => ({ ...test, testID: `t${index}`, testName: `Quiz ${index}` })),
            );
            await wrapper
                .findAll('.page-link')
                .find((link) => link.text() === 'Next')
                .trigger('click');

            // Act
            await wrapper.get('#testSelectionSearch').setValue('quiz');

            // Assert
            expect(titles(wrapper)[0]).toBe('Quiz 0');
        });
    });
});
