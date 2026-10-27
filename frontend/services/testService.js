import api from './api';

export async function getAvailableTests() {
    const response = await api.get('/tests');
    return response.data;
}

// Starts an attempt, the backend draws the test's questions at random from its pool
// and sends them with their answer options, the correct answers aren't included.
export async function startTestAttempt(testID) {
    const response = await api.post(`/tests/${encodeURIComponent(testID)}/start`);
    return response.data;
}

// answers is { questionID: answerID or null }, the backend marks them and
// sends back the score with the correct answer for every question.
export async function submitTest(testID, answers) {
    const response = await api.post(`/tests/${encodeURIComponent(testID)}/complete`, { answers });
    return response.data;
}
