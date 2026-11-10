/// @vitest-environment jsdom

import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import QuestionManagementTable from '../components/tables/questionManagementTable.vue';
import { getLecturerQuestions } from '../services/questionService';

vi.mock('datatables.net-vue3', async () => {
    const { defineComponent: define, h: createElement } = await import('vue');

    const DataTable = define({
        name: 'DataTable',
        props: {
            columns: Array,
            data: Array,
            options: Object,
        },
        setup(props, { slots, expose }) {
            expose({
                dt: {
                    draw: vi.fn(),
                    column: vi.fn(() => ({
                        search: vi.fn().mockReturnThis(),
                        draw: vi.fn(),
                    })),
                },
            });

            return () =>
                createElement('table', [
                    createElement('thead', [
                        createElement(
                            'tr',
                            props.columns.map((column) => createElement('th', column.title)),
                        ),
                    ]),
                    createElement(
                        'tbody',
                        (props.data ?? []).map((row) =>
                            createElement(
                                'tr',
                                { key: row.questionID },
                                props.columns.map((column) => {
                                    if (column.render === '#actions') {
                                        return createElement('td', slots.actions?.({ rowData: row }));
                                    }

                                    const value = column.data ? row[column.data] : null;

                                    const displayValue =
                                        typeof column.render === 'function'
                                            ? column.render(value, 'display', row)
                                            : value;

                                    return createElement('td', String(displayValue ?? ''));
                                }),
                            ),
                        ),
                    ),
                ]);
        },
    });

    DataTable.use = vi.fn();

    return { default: DataTable };
});

vi.mock('datatables.net-bs5', () => ({
    default: {
        render: {
            text: () => (value) => value,
        },
        util: {
            escapeHtml: (value) =>
                String(value)
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#39;'),
        },
    },
}));

vi.mock('../services/questionService', () => ({
    getLecturerQuestions: vi.fn(),
}));

const questions = [
    {
        questionID: 'question-1',
        question: 'What is HTML?',
        answers: [{ answerText: 'HyperText Markup Language' }, { answerText: 'HighText Machine Language' }],
        correctAnswer: 'HyperText Markup Language',
        subjectName: 'Web Development',
        percentageCorrect: 85,
    },
    {
        questionID: 'question-2',
        question: 'What is CSS?',
        answers: [{ answerText: 'Cascading Style Sheets' }, { answerText: 'Computer Style Sheets' }],
        correctAnswer: 'Cascading Style Sheets',
        subjectName: 'Web Development',
        percentageCorrect: 70,
    },
    {
        questionID: 'question-3',
        question: 'What is PHP?',
        answers: [{ answerText: 'PHP: Hypertext Preprocessor' }, { answerText: 'Personal Home Page' }],
        correctAnswer: 'PHP: Hypertext Preprocessor',
        subjectName: 'Programming',
        percentageCorrect: null,
    },
];

describe('QuestionManagementTable', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.mocked(getLecturerQuestions).mockResolvedValue(questions);
    });

    async function mountTable() {
        const wrapper = mount(QuestionManagementTable);
        await flushPromises();

        return wrapper;
    }

    it('retrieves lecturer questions when the table loads', async () => {
        // Arrange
        await mountTable();

        // Assert
        expect(getLecturerQuestions).toHaveBeenCalledOnce();
    });

    it('displays the question management table columns', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Assert
        expect(wrapper.text()).toContain('Question');
        expect(wrapper.text()).toContain('Answers');
        expect(wrapper.text()).toContain('Correct Answer');
        expect(wrapper.text()).toContain('Subject');
        expect(wrapper.text()).toContain('Percentage Correct');
        expect(wrapper.text()).toContain('Actions');
    });

    it('displays the questions and their details', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Assert
        expect(wrapper.text()).toContain('What is HTML?');
        expect(wrapper.text()).toContain('What is CSS?');
        expect(wrapper.text()).toContain('What is PHP?');
        expect(wrapper.text()).toContain('HyperText Markup Language');
        expect(wrapper.text()).toContain('Cascading Style Sheets');
        expect(wrapper.text()).toContain('Web Development');
        expect(wrapper.text()).toContain('Programming');
    });

    it('joins multiple answers together', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Assert
        expect(wrapper.text()).toContain('HyperText Markup Language | HighText Machine Language');
        expect(wrapper.text()).toContain('Cascading Style Sheets | Computer Style Sheets');
    });

    it('displays percentage correct values and handles missing percentages', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Assert
        expect(wrapper.text()).toContain('85%');
        expect(wrapper.text()).toContain('70%');
        expect(wrapper.text()).toContain('What is PHP?');
    });

    it('provides Edit and Delete actions for each question', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Assert
        expect(wrapper.findAll('button[aria-label^="Edit question:"]')).toHaveLength(3);
        expect(wrapper.findAll('button[aria-label^="Delete question:"]')).toHaveLength(3);
    });

    it('emits the selected question when Edit is clicked', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Act
        await wrapper.get('button[aria-label="Edit question: What is HTML?"]').trigger('click');

        // Assert
        expect(wrapper.emitted('editQuestion')).toEqual([[questions[0]]]);
    });

    it('emits the selected question when Delete is clicked', async () => {
        // Arrange
        const wrapper = await mountTable();

        // Act
        await wrapper.get('button[aria-label="Delete question: What is CSS?"]').trigger('click');

        // Assert
        expect(wrapper.emitted('deleteQuestion')).toEqual([[questions[1]]]);
    });

    it('displays an error when questions cannot be loaded', async () => {
        // Arrange
        vi.mocked(getLecturerQuestions).mockRejectedValue(new Error('API error'));

        // Act
        const wrapper = mount(QuestionManagementTable);
        await flushPromises();

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toBe('Unable to load questions. Please try again.');
    });
});
