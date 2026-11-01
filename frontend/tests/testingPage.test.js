// @vitest-environment jsdom

import { flushPromises, mount, RouterLinkStub } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import TestingPage from '../views/testingPage.vue';
import { startTestAttempt, submitTest } from '../services/testService';

vi.mock('../services/testService', () => ({
    startTestAttempt: vi.fn(),
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
        startTestAttempt.mockResolvedValue(structuredClone(test));
        submitTest.mockResolvedValue(structuredClone(result));
        // 0.99 makes the shuffle swap nothing, so the answers stay in the order above
        vi.spyOn(Math, 'random').mockReturnValue(0.99);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.restoreAllMocks();
    });

    it('shuffles the answer boxes and keeps that order in the review', async () => {
        // Arrange
        // 0 swaps every pair, so each question's two answers are reversed
        Math.random.mockReturnValue(0);
        const wrapper = await mountPage();
        await wrapper.get('.testingPrimaryButton').trigger('click');

        // Act
        const shownAnswers = wrapper.findAll('.answerText').map((answer) => answer.text());

        // the first box is now Vue, so clicking it sends a2
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await flushPromises();

        // Assert
        expect(shownAnswers).toEqual(['Vue', 'Django']);
        expect(submitTest).toHaveBeenCalledWith('test-1', { 'question-1': 'a2', 'question-2': 'b2' });
        const reviewAnswers = wrapper.findAll('.reviewQuestion')[0].findAll('.reviewAnswer');
        expect(reviewAnswers.map((answer) => answer.text())).toEqual([
            expect.stringContaining('Vue'),
            expect.stringContaining('Django'),
        ]);
    });

    it('loads the chosen test and shows a start screen first', async () => {
        // Arrange: beforeEach sets the test to load

        // Act
        const wrapper = await mountPage();

        // Assert
        expect(startTestAttempt).toHaveBeenCalledWith('test-1');
        expect(wrapper.get('h1').text()).toBe('Web Apps basics');
        expect(wrapper.text()).toContain('Web Apps');
        expect(wrapper.text()).toContain('5 minutes');
        expect(wrapper.find('.answerButton').exists()).toBe(false);
    });

    it('says when a test has no time limit and hides the timer', async () => {
        // Arrange
        startTestAttempt.mockResolvedValue({ ...test, timeLimit: null });

        // Act
        const wrapper = await mountPage();
        const startScreenText = wrapper.text();
        await wrapper.get('.testingPrimaryButton').trigger('click');

        // Assert
        expect(startScreenText).toContain('No time limit');
        expect(wrapper.find('.testingTimer').exists()).toBe(false);
    });

    it('moves to the next question after each answer, then shows the mark and review', async () => {
        // Arrange
        const wrapper = await mountPage();
        await wrapper.get('.testingPrimaryButton').trigger('click');

        // Act
        const firstQuestion = wrapper.get('.questionText').text();
        const firstProgress = wrapper.get('.testingProgress').text();

        await wrapper.findAll('.answerButton')[0].trigger('click');
        const secondQuestion = wrapper.get('.questionText').text();

        await wrapper.findAll('.answerButton')[1].trigger('click');
        await flushPromises();

        // Assert
        expect(firstQuestion).toBe('What is not a framework for JS?');
        expect(firstProgress).toBe('1/ 2 Questions');
        expect(secondQuestion).toBe('Which is a CSS unit?');
        expect(submitTest).toHaveBeenCalledWith('test-1', { 'question-1': 'a1', 'question-2': 'b2' });
        expect(wrapper.get('.resultsMark').text()).toContain('1/2');
        expect(wrapper.text()).toContain('+20 PTS');
        expect(wrapper.find('.fullMarksNote').exists()).toBe(false);

        // the wrong answer is shown alongside the correct one
        const wrongQuestion = wrapper.findAll('.reviewQuestion')[1];
        expect(wrongQuestion.get('.reviewStatus').text()).toBe('Incorrect');
        expect(wrongQuestion.get('.reviewAnswerCorrect').text()).toContain('rem');
        expect(wrongQuestion.get('.reviewAnswerCorrect').text()).toContain('Correct answer');
        expect(wrongQuestion.get('.reviewAnswerWrong').text()).toContain('Your answer');
    });

    it('tells the student a full marks test can be practised without earning more points', async () => {
        // Arrange
        const fullMarks = structuredClone(result);
        fullMarks.correctCount = 2;
        fullMarks.questions[1].isCorrect = true;
        submitTest.mockResolvedValue(fullMarks);
        const wrapper = await mountPage();

        // Act
        await wrapper.get('.testingPrimaryButton').trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await flushPromises();

        // Assert
        expect(wrapper.get('.fullMarksNote').text()).toContain("retakes won't earn points");
    });

    it('marks a retake after full marks as practice, before and after the test', async () => {
        // Arrange
        startTestAttempt.mockResolvedValue({ ...test, earnsPoints: false });
        const practice = structuredClone(result);
        practice.earnsPoints = false;
        practice.pointsEarned = 0;
        practice.questions.forEach((question) => {
            question.pointsAwarded = 0;
        });
        submitTest.mockResolvedValue(practice);

        // Act
        const wrapper = await mountPage();
        const noteBeforeTest = wrapper.get('.fullMarksNote').text();

        await wrapper.get('.testingPrimaryButton').trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await wrapper.findAll('.answerButton')[1].trigger('click');
        await flushPromises();

        // Assert
        expect(noteBeforeTest).toContain("won't earn points");
        expect(wrapper.get('.fullMarksNote').text()).toContain('no points were added');
        expect(wrapper.text()).toContain('0 PTS');
    });

    it('hands the test in when the timer runs out, unanswered questions as null', async () => {
        // Arrange
        vi.useFakeTimers();
        startTestAttempt.mockResolvedValue({ ...test, timeLimit: 1 });
        const wrapper = await mountPage();

        await wrapper.get('.testingPrimaryButton').trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');

        // Act
        await vi.advanceTimersByTimeAsync(60 * 1000);

        // Assert
        expect(submitTest).toHaveBeenCalledOnce();
        expect(submitTest).toHaveBeenCalledWith('test-1', { 'question-1': 'a1', 'question-2': null });
    });

    it('keeps the answers and lets the student try again if saving fails', async () => {
        // Arrange
        submitTest.mockRejectedValueOnce({ response: { data: { error: 'Unable to save your test.' } } });
        const wrapper = await mountPage();

        await wrapper.get('.testingPrimaryButton').trigger('click');
        await wrapper.findAll('.answerButton')[0].trigger('click');

        // Act
        await wrapper.findAll('.answerButton')[0].trigger('click');
        await flushPromises();
        const errorAfterFailedSave = wrapper.get('[role="alert"]').text();

        await wrapper.get('.testingPrimaryButton').trigger('click');
        await flushPromises();

        // Assert
        expect(errorAfterFailedSave).toBe('Unable to save your test.');
        expect(submitTest).toHaveBeenCalledTimes(2);
        expect(submitTest.mock.calls[1]).toEqual(submitTest.mock.calls[0]);
        expect(wrapper.find('.resultsMark').exists()).toBe(true);
    });

    it('shows the API error when the test cannot be loaded', async () => {
        // Arrange
        startTestAttempt.mockRejectedValue({ response: { data: { error: 'This test is not available.' } } });

        // Act
        const wrapper = await mountPage();

        // Assert
        expect(wrapper.get('[role="alert"]').text()).toBe('This test is not available.');
    });
});
