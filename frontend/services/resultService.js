import api from './api';

export async function getCompletedTests() {
    const response = await api.get('/tests/completed');
    return response.data;
}

// The logged in student's details and totals for the top of the results page.
export async function getResultsSummary() {
    const response = await api.get('/results/summary');
    return response.data;
}

// One completed attempt with every question, the student's answer and the correct answer.
export async function getCompletedTestReview(completedID) {
    const response = await api.get(`/tests/completed/${encodeURIComponent(completedID)}`);
    return response.data;
}
