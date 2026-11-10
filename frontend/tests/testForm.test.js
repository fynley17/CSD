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
    questionSelectionMethod: 'frequently_incorrect',
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

    it('defaults to random question selection when creating a test', async () => {
        const wrapper = mountForm();
        await flushPromises();

        expect(wrapper.get('#questionSelectionMethod').element.value).toBe('random');
        expect(wrapper.text()).toContain("Questions are selected randomly from the subject's question pool.");
    });

    it('provides both question selection methods', async () => {
        const wrapper = mountForm();
        await flushPromises();

        const options = wrapper.findAll('#questionSelectionMethod option');

        expect(options).toHaveLength(2);
        expect(options.map((option) => option.element.value)).toEqual(['random', 'frequently_incorrect']);
    });

    it('retains frequently incorrect selection when editing an existing test', async () => {
        const wrapper = mountForm(existingTest);
        await flushPromises();

        expect(wrapper.get('#questionSelectionMethod').element.value).toBe('frequently_incorrect');
        expect(wrapper.get('#testName').element.value).toBe('Existing Test');
        expect(wrapper.text()).toContain('Personalised selection starts after three completed attempts.');
    });

    it('shows the correct explanation when the selection method changes', async () => {
        const wrapper = mountForm();
        await flushPromises();

        await wrapper.get('#questionSelectionMethod').setValue('frequently_incorrect');

        expect(wrapper.text()).toContain('Prioritises questions each student has previously answered incorrectly.');
        expect(wrapper.text()).toContain('Personalised selection starts after three completed attempts.');
    });

    it('creates a test using the selected question selection method', async () => {
        const wrapper = mountForm();
        await flushPromises();

        await wrapper.get('#testName').setValue('New Test');
        await wrapper.get('#subjectID').setValue('subject-1');
        await wrapper.get('#description').setValue('Test description');
        await wrapper.get('#questionCount').setValue('5');
        await wrapper.get('#timeLimit').setValue('30');
        await wrapper.get('#questionSelectionMethod').setValue('frequently_incorrect');
        await wrapper.get('#dueDate').setValue('2026-12-01');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(createTest).toHaveBeenCalledWith({
            testName: 'New Test',
            subjectID: 'subject-1',
            testDescription: 'Test description',
            timeLimit: 30,
            dueDate: '2026-12-01',
            questionCount: 5,
            questionSelectionMethod: 'frequently_incorrect',
        });

        expect(wrapper.emitted('saved')).toHaveLength(1);
    });

    it('updates an existing test using the selected question selection method', async () => {
        const wrapper = mountForm(existingTest);
        await flushPromises();

        await wrapper.get('#questionSelectionMethod').setValue('random');

        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(updateTest).toHaveBeenCalledWith('test-1', {
            testName: 'Existing Test',
            subjectID: 'subject-1',
            testDescription: 'Existing description',
            timeLimit: 30,
            dueDate: '2026-12-01',
            questionCount: 5,
            questionSelectionMethod: 'random',
        });

        expect(wrapper.emitted('saved')).toHaveLength(1);
    });
});
