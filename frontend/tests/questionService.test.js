import { describe, it, expect, vi, beforeEach } from 'vitest';

import api from '../services/api';

import { getLecturerQuestions, createQuestion, updateQuestion, deleteQuestion } from '../services/questionService';

vi.mock('../services/api', () => ({
    default: {
        get: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

describe('questionService', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('fetches all lecturer questions', async () => {
        // Arrange
        const questions = [{ questionID: '123', question: 'Example question' }];
        api.get.mockResolvedValue({ data: questions });

        // Act
        const result = await getLecturerQuestions();

        // Assert
        expect(api.get).toHaveBeenCalledWith('/lecturer/questions');
        expect(result).toEqual(questions);
    });

    it('creates a question', async () => {
        // Arrange
        const questionData = {
            subjectID: 'subject-123',
            question: 'Example question',
            answers: [
                { answerText: 'Answer 1', isCorrect: true },
                { answerText: 'Answer 2', isCorrect: false },
                { answerText: 'Answer 3', isCorrect: false },
                { answerText: 'Answer 4', isCorrect: false },
            ],
        };
        const responseData = { questionID: 'question-123' };
        api.post.mockResolvedValue({ data: responseData });

        // Act
        const result = await createQuestion(questionData);

        // Assert
        expect(api.post).toHaveBeenCalledWith('/lecturer/questions', questionData);
        expect(result).toEqual(responseData);
    });

    it('updates an existing question', async () => {
        // Arrange
        const questionID = 'question-123';
        const questionData = {
            subjectID: 'subject-123',
            question: 'Updated question',
            answers: [],
        };
        const responseData = { message: 'Question updated successfully.' };
        api.put.mockResolvedValue({ data: responseData });

        // Act
        const result = await updateQuestion(questionID, questionData);

        // Assert
        expect(api.put).toHaveBeenCalledWith('/lecturer/questions/question-123', questionData);
        expect(result).toEqual(responseData);
    });

    it('deletes an existing question', async () => {
        // Arrange
        const questionID = 'question-123';
        const responseData = { message: 'Question deleted successfully.' };
        api.delete.mockResolvedValue({ data: responseData });

        // Act
        const result = await deleteQuestion(questionID);

        // Assert
        expect(api.delete).toHaveBeenCalledWith('/lecturer/questions/question-123');
        expect(result).toEqual(responseData);
    });
});
