// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TestForm from '../components/forms/testForm.vue';
import { createTest, getActiveTestSubjects, updateTest } from '../services/testService';

vi.mock('../services/testService', () => ({
    createTest: vi.fn(),
    getActiveTestSubjects: vi.fn(),
    updateTest: vi.fn(),
}));

// Provide the dialog methods used by TestForm without relying on native dialog support.
const PopupDialogStub = defineComponent({
    props: ['title'],
    setup(props, { slots, expose }) {
        expose({
            open: vi.fn(),
            close: vi.fn(),
        });

        return () => h('div', [h('h2', props.title), slots.default?.(), slots.buttons?.()]);
    },
});

const subjects = [
    {
        subjectID: 'subject-1',
        subjectName: 'Mathematics',
        cohortName: 'Cohort A',
        questionPoolSize: 20,
        isActive: true,
    },
];

const existingTest = {
    testID: 'test-1',
    testName: 'Existing Test',
    subjectID: 'subject-1',
    subjectName: 'Mathematics',
    cohortName: 'Cohort A',
    testDescription: 'Existing description',
    timeLimit: 30,
    dueDate: '2026-12-01',
    questionCount: 5,
};

describe('TestForm', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        vi.mocked(getActiveTestSubjects).mockResolvedValue(subjects);
        vi.mocked(createTest).mockResolvedValue({ message: 'Test created' });
        vi.mocked(updateTest).mockResolvedValue({ message: 'Test updated' });
    });

    function mountForm(test = null) {
        return mount(TestForm, {
            props: { test },
            global: {
                stubs: {
                    PopupDialog: PopupDialogStub,
                },
            },
        });
    }

    it('does not display a question selection method field', async () => {
        // Arrange
        const wrapper = mountForm();
        await flushPromises();

        // Act
        const selectionField = wrapper.find('#questionSelectionMethod');

        // Assert
        expect(selectionField.exists()).toBe(false);
    });

    it('creates a test without a question selection method', async () => {
        // Arrange
        const wrapper = mountForm();
        await flushPromises();

        await wrapper.get('#testName').setValue('New Test');
        await wrapper.get('#subjectID').setValue('subject-1');
        await wrapper.get('#description').setValue('Test description');
        await wrapper.get('#questionCount').setValue('5');
        await wrapper.get('#timeLimit').setValue('30');
        await wrapper.get('#dueDate').setValue('2026-12-01');

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(createTest).toHaveBeenCalledWith({
            testName: 'New Test',
            subjectID: 'subject-1',
            testDescription: 'Test description',
            timeLimit: 30,
            dueDate: '2026-12-01',
            questionCount: 5,
        });

        expect(wrapper.emitted('saved')).toHaveLength(1);
    });

    it('updates an existing test without a question selection method', async () => {
        // Arrange
        const wrapper = mountForm(existingTest);
        await flushPromises();

        // Act
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        // Assert
        expect(updateTest).toHaveBeenCalledWith('test-1', {
            testName: 'Existing Test',
            subjectID: 'subject-1',
            testDescription: 'Existing description',
            timeLimit: 30,
            dueDate: '2026-12-01',
            questionCount: 5,
        });

        expect(wrapper.emitted('saved')).toHaveLength(1);
    });
});
