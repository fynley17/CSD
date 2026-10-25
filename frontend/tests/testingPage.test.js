// @vitest-environment jsdom

import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TestingPage from '../views/testingPage.vue';
import { getTest, submitTest } from '../services/testService';

vi.mock('../services/testService', () => ({
    getTest: vi.fn(),
    submitTest: vi.fn(),
}));

vi.mock('vue-router', () => ({
    useRoute: () => ({ params: { testID: 'test-1' } }),
}));

const test = {
    testID: 'test-1',
    testName: 'Web Apps basics',
    subjectName: 'Web Apps',
    testDescription: 'Review the basics.',
    timeLimit: 5,
    questionCount: 2,
    questions: [
        {
            questionID: 'question-1',
            question: 'What is not a framework for JS?',
            answers: [
                { answerID: 'a1', answerText: 'Django' },
                { answerID: 'a2', answerText: 'Vue' },
            ],
        },
        {
            questionID: 'question-2',
            question: 'Which is a CSS unit?',
            answers: [
                { answerID: 'b1', answerText: 'rem' },
                { answerID: 'b2', answerText: 'kg' },
            ],
        },
    ],
};

const result = {
    questionCount: 2,
    correctCount: 1,
    pointsEarned: 20,
    percentageCorrect: 50,
    questions: [
        {
            questionID: 'question-1',
            question: 'What is not a framework for JS?',
            selectedAnswerID: 'a1',
            isCorrect: true,
            pointsAwarded: 30,
            answers: [
                { answerID: 'a1', answerText: 'Django', isCorrect: true },
                { answerID: 'a2', answerText: 'Vue', isCorrect: false },
            ],
        },
        {
            questionID: 'question-2',
            question: 'Which is a CSS unit?',
            selectedAnswerID: 'b2',
            isCorrect: false,
            pointsAwarded: -10,
            answers: [
                { answerID: 'b1', answerText: 'rem', isCorrect: true },
                { answerID: 'b2', answerText: 'kg', isCorrect: false },
            ],
        },
    ],
};

async function mountPage() {
    const wrapper = mount(TestingPage, { global: { stubs: { RouterLink: RouterLinkStub } } });
    await flushPromises();
    return wrapper;
}

describe('TestingPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        getTest.mockResolvedValue(structuredClone(test));
        submitTest.mockResolvedValue(structuredClone(result));
        // 0.99 makes the shuffle swap nothing, so the answers stay in the order above
        vi.spyOn(Math, 'random').mockReturnValue(0.99);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('shuffles the answer boxes and keeps that order in the review', async () => {
        // 0 swaps every pair, so each question's two answers are reversed
        Math.random.mockReturnValue(0);
        const wrapper = await mountPage();
        await wrapper.get('.testingPrimaryButton').trigger('click');

        const shownAnswers = wrapper.findAll('.answerText').map((answer) => answer.text());
        expect(shownAnswers).toEqual(['Vue', 'Django']);

        // the first box is now Vue, so clicking it sends a2
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await flushPromises();

        expect(submitTest).toHaveBeenCalledWith('test-1', { 'question-1': 'a2', 'question-2': 'b2' });
        const reviewAnswers = wrapper.findAll('.reviewQuestion')[0].findAll('.reviewAnswer');
        expect(reviewAnswers.map((answer) => answer.text())).toEqual([
            expect.stringContaining('Vue'),
            expect.stringContaining('Django'),
        ]);
    });

    it('loads the chosen test and shows a start screen first', async () => {
        const wrapper = await mountPage();

        expect(getTest).toHaveBeenCalledWith('test-1');
        expect(wrapper.get('h1').text()).toBe('Web Apps basics');
        expect(wrapper.text()).toContain('Web Apps');
        expect(wrapper.text()).toContain('5 minutes');
        expect(wrapper.find('.answerButton').exists()).toBe(false);
    });

    it('says when a test has no time limit and hides the timer', async () => {
        getTest.mockResolvedValue({ ...test, timeLimit: null });
        const wrapper = await mountPage();

        expect(wrapper.text()).toContain('No time limit');

        await wrapper.get('.testingPrimaryButton').trigger('click');
        expect(wrapper.find('.testingTimer').exists()).toBe(false);
    });

    it('moves to the next question after each answer, then shows the mark and review', async () => {
        const wrapper = await mountPage();
        await wrapper.get('.testingPrimaryButton').trigger('click');

        expect(wrapper.get('.questionText').text()).toBe('What is not a framework for JS?');
        expect(wrapper.get('.testingProgress').text()).toBe('1/ 2 Questions');

        await wrapper.findAll('.answerButton')[0].trigger('click');
        expect(wrapper.get('.questionText').text()).toBe('Which is a CSS unit?');

        await wrapper.findAll('.answerButton')[1].trigger('click');
        await flushPromises();

        expect(submitTest).toHaveBeenCalledWith('test-1', { 'question-1': 'a1', 'question-2': 'b2' });
        expect(wrapper.get('.resultsMark').text()).toContain('1/2');
        expect(wrapper.text()).toContain('+20 PTS');

        // the wrong answer is shown alongside the correct one
        const wrongQuestion = wrapper.findAll('.reviewQuestion')[1];
        expect(wrongQuestion.get('.reviewStatus').text()).toBe('Incorrect');
        expect(wrongQuestion.get('.reviewAnswerCorrect').text()).toContain('rem');
        expect(wrongQuestion.get('.reviewAnswerCorrect').text()).toContain('Correct answer');
        expect(wrongQuestion.get('.reviewAnswerWrong').text()).toContain('Your answer');
    });

    it('hands the test in when the timer runs out, unanswered questions as null', async () => {
        vi.useFakeTimers();
        getTest.mockResolvedValue({ ...test, timeLimit: 1 });
        const wrapper = await mountPage();

        await wrapper.get('.testingPrimaryButton').trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');

        await vi.advanceTimersByTimeAsync(60 * 1000);

        expect(submitTest).toHaveBeenCalledOnce();
        expect(submitTest).toHaveBeenCalledWith('test-1', { 'question-1': 'a1', 'question-2': null });
    });

    it('keeps the answers and lets the student try again if saving fails', async () => {
        submitTest.mockRejectedValueOnce({ response: { data: { error: 'Unable to save your test.' } } });
        const wrapper = await mountPage();

        await wrapper.get('.testingPrimaryButton').trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('Unable to save your test.');

        await wrapper.get('.testingPrimaryButton').trigger('click');
        await flushPromises();

        expect(submitTest).toHaveBeenCalledTimes(2);
        expect(submitTest.mock.calls[1]).toEqual(submitTest.mock.calls[0]);
        expect(wrapper.find('.resultsMark').exists()).toBe(true);
    });

    it('shows the API error when the test cannot be loaded', async () => {
        getTest.mockRejectedValue({ response: { data: { error: 'This test is not available.' } } });
        const wrapper = await mountPage();

        expect(wrapper.get('[role="alert"]').text()).toBe('This test is not available.');
    });
});
