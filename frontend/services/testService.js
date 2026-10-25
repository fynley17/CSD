import api from './api';

export async function getAvailableTests() {
    const response = await api.get('/tests');
    return response.data;
}

// One test with its questions and answer options, the correct answers aren't included.
export async function getTest(testID) {
    const response = await api.get(`/tests/${encodeURIComponent(testID)}`);
    return response.data;
}

// answers is { questionID: answerID or null }, the backend marks them and
// sends back the score with the correct answer for every question.
export async function submitTest(testID, answers) {
    const response = await api.post(`/tests/${encodeURIComponent(testID)}/complete`, { answers });
    return response.data;
}
