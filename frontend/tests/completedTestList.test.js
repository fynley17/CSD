// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import CompletedTestList from '../components/common/completedTestList.vue';

vi.mock('../services/resultService', () => ({
    getCompletedTestReview: vi.fn(),
}));

// newest first, as the API sends them
function makeTests(count) {
    return Array.from({ length: count }, (_, index) => ({
        completedID: `completed-${index + 1}`,
        testName: `Test ${index + 1}`,
        testDescription: 'Review',
        subjectName: 'Maths',
        completedDate: '2026-10-20 09:00:00',
        questionCount: 10,
        pointsEarned: 30,
        percentageCorrect: 50,
    }));
}

function titles(wrapper) {
    return wrapper.findAll('.completedTestTitle').map((title) => title.text());
}

function clickButton(wrapper, text) {
    return wrapper
        .findAll('.page-link')
        .find((button) => button.text() === text)
        .trigger('click');
}

describe('completedTestList', () => {
    it('shows the first 5 tests in the order they were given', () => {
        // Arrange
        const tests = makeTests(12);

        // Act
        const wrapper = mount(CompletedTestList, { props: { tests } });

        // Assert
        expect(titles(wrapper)).toEqual(['Test 1', 'Test 2', 'Test 3', 'Test 4', 'Test 5']);
        expect(wrapper.text()).toContain('Showing 1 to 5 of 12 tests');
    });

    it('has no page options when everything fits on one page', () => {
        // Arrange
        const tests = makeTests(5);

        // Act
        const wrapper = mount(CompletedTestList, { props: { tests } });

        // Assert
        expect(titles(wrapper)).toHaveLength(5);
        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('moves between pages with the numbers and next and previous', async () => {
        // Arrange
        const wrapper = mount(CompletedTestList, { props: { tests: makeTests(12) } });

        // Act
        await clickButton(wrapper, '3');
        const lastPageTitles = titles(wrapper);
        const currentPage = wrapper.find('[aria-current="page"]').text();
        const lastPageText = wrapper.text();

        await clickButton(wrapper, 'Previous');
        const firstTitleAfterPrevious = titles(wrapper)[0];

        await clickButton(wrapper, 'Next');
        const firstTitleAfterNext = titles(wrapper)[0];

        // Assert
        expect(lastPageTitles).toEqual(['Test 11', 'Test 12']);
        expect(currentPage).toBe('3');
        expect(lastPageText).toContain('Showing 11 to 12 of 12 tests');
        expect(firstTitleAfterPrevious).toBe('Test 6');
        expect(firstTitleAfterNext).toBe('Test 11');
    });

    it('turns off previous on the first page and next on the last', async () => {
        // Arrange
        const wrapper = mount(CompletedTestList, { props: { tests: makeTests(6) } });
        const button = (text) => wrapper.findAll('.page-link').find((link) => link.text() === text);

        // Act
        const previousOnFirstPage = button('Previous').attributes('disabled');
        const nextOnFirstPage = button('Next').attributes('disabled');

        await clickButton(wrapper, 'Next');
        const previousOnLastPage = button('Previous').attributes('disabled');
        const nextOnLastPage = button('Next').attributes('disabled');

        // Assert
        expect(previousOnFirstPage).toBeDefined();
        expect(nextOnFirstPage).toBeUndefined();
        expect(previousOnLastPage).toBeUndefined();
        expect(nextOnLastPage).toBeDefined();
    });

    it('stays on a real page when the list gets shorter', async () => {
        // Arrange
        const wrapper = mount(CompletedTestList, { props: { tests: makeTests(12) } });
        await clickButton(wrapper, '3');

        // Act
        await wrapper.setProps({ tests: makeTests(7) });

        // Assert
        expect(titles(wrapper)).toEqual(['Test 6', 'Test 7']);
    });
});
