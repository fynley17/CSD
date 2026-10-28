import api from './api';

// Fetch all questions for the lecturer question management page.
export async function getLecturerQuestions() {
    const response = await api.get('/lecturer/questions');
    return response.data;
}
