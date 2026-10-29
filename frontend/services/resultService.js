import api from './api';

export async function getCompletedTests() {
    const response = await api.get('/tests/completed');
    return response.data;
}

// One completed attempt with every question, the student's answer and the correct answer.
export async function getCompletedTestReview(completedID) {
    const response = await api.get(`/tests/completed/${encodeURIComponent(completedID)}`);
    return response.data;
}
