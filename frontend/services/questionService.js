import api from './api';

// Fetch all questions for the lecturer question management page.
export async function getLecturerQuestions() {
    const response = await api.get('/lecturer/questions');
    return response.data;
}

// Create a new question.
export async function createQuestion(questionData) {
    const response = await api.post('/lecturer/questions', questionData);
    return response.data;
}

// Update an existing question.
export async function updateQuestion(questionID, questionData) {
    const response = await api.put(`/lecturer/questions/${encodeURIComponent(questionID)}`, questionData);
    return response.data;
}

// Delete an existing question.
export async function deleteQuestion(questionID) {
    const response = await api.delete(`/lecturer/questions/${encodeURIComponent(questionID)}`);
    return response.data;
}
