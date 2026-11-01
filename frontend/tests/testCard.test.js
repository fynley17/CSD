// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import TestCard from '../components/common/testCard.vue';

const baseTest = {
    testID: 'test-1',
    testName: 'Web Apps Fundamentals',
    testDescription: 'Review core web application concepts.',
    questionCount: 15,
    subjectName: 'Web Apps',
    createdByName: 'Alex Lecturer',
    timeLimit: 75,
    dueDate: '2026-10-22',
};

describe('TestCard', () => {
    it('displays the test information in the requested order', () => {
        // Arrange: baseTest is the test to show

        // Act
        const wrapper = mount(TestCard, { props: { test: baseTest } });

        // Assert
        expect(wrapper.get('h2').text()).toBe(baseTest.testName);
        expect(wrapper.get('.testCardDescription').text()).toBe(baseTest.testDescription);

        const details = wrapper.findAll('.testCardDetails dt').map((element) => element.text());
        expect(details).toEqual(['Time limit', 'Questions', 'Subject', 'Set by', 'Due by']);

        const values = wrapper.findAll('.testCardDetails dd').map((element) => element.text());
        expect(values).toEqual([
            '1 hour 15 minutes',
            '15',
            'Web Apps',
            'Alex Lecturer',
            new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(baseTest.dueDate)),
        ]);
    });

    it('omits the optional time limit and due date when absent', () => {
        // Arrange
        const test = { ...baseTest, timeLimit: null, dueDate: null };

        // Act
        const wrapper = mount(TestCard, { props: { test } });

        // Assert
        const details = wrapper.findAll('.testCardDetails dt').map((element) => element.text());
        expect(details).toEqual(['Questions', 'Subject', 'Set by']);
    });

    it('only shows the practice note once the student has full marks', () => {
        // Arrange
        const practiceTest = { ...baseTest, earnsPoints: false };

        // Act
        const normalCard = mount(TestCard, { props: { test: baseTest } });
        const practiceCard = mount(TestCard, { props: { test: practiceTest } });

        // Assert
        expect(normalCard.find('.testCardPracticeNote').exists()).toBe(false);
        expect(practiceCard.get('.testCardPracticeNote').text()).toContain("don't earn points");
        // it can still be started
        expect(practiceCard.find('.testCardStartButton').exists()).toBe(true);
    });

    it('emits the selected test when Start test is clicked', async () => {
        // Arrange
        const wrapper = mount(TestCard, { props: { test: baseTest } });

        // Act
        await wrapper.get('.testCardStartButton').trigger('click');

        // Assert
        expect(wrapper.emitted('start')).toEqual([[baseTest]]);
    });
});
