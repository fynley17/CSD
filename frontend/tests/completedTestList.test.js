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
        const wrapper = mount(CompletedTestList, { props: { tests: makeTests(12) } });

        expect(titles(wrapper)).toEqual(['Test 1', 'Test 2', 'Test 3', 'Test 4', 'Test 5']);
        expect(wrapper.text()).toContain('Showing 1 to 5 of 12 tests');
    });

    it('has no page options when everything fits on one page', () => {
        const wrapper = mount(CompletedTestList, { props: { tests: makeTests(5) } });

        expect(titles(wrapper)).toHaveLength(5);
        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('moves between pages with the numbers and next and previous', async () => {
        const wrapper = mount(CompletedTestList, { props: { tests: makeTests(12) } });

        await clickButton(wrapper, '3');
        expect(titles(wrapper)).toEqual(['Test 11', 'Test 12']);
        expect(wrapper.find('[aria-current="page"]').text()).toBe('3');
        expect(wrapper.text()).toContain('Showing 11 to 12 of 12 tests');

        await clickButton(wrapper, 'Previous');
        expect(titles(wrapper)[0]).toBe('Test 6');

        await clickButton(wrapper, 'Next');
        expect(titles(wrapper)[0]).toBe('Test 11');
    });

    it('turns off previous on the first page and next on the last', async () => {
        const wrapper = mount(CompletedTestList, { props: { tests: makeTests(6) } });
        const button = (text) => wrapper.findAll('.page-link').find((link) => link.text() === text);

        expect(button('Previous').attributes('disabled')).toBeDefined();
        expect(button('Next').attributes('disabled')).toBeUndefined();

        await clickButton(wrapper, 'Next');

        expect(button('Previous').attributes('disabled')).toBeUndefined();
        expect(button('Next').attributes('disabled')).toBeDefined();
    });

    it('stays on a real page when the list gets shorter', async () => {
        const wrapper = mount(CompletedTestList, { props: { tests: makeTests(12) } });

        await clickButton(wrapper, '3');
        await wrapper.setProps({ tests: makeTests(7) });

        expect(titles(wrapper)).toEqual(['Test 6', 'Test 7']);
    });
});
