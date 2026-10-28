import api from './api';

export async function getDashboardSummary() {
    const response = await api.get('/lecturer/dashboard-summary');
    return response.data;
}

export async function getRecentStudentActivity() {
    const response = await api.get('/lecturer/recent-students');
    return response.data;
}

// Fetch all tests for the lecturer dashboard.
export async function getLecturerTests() {
    const response = await api.get('/lecturer/tests');
    return response.data;
}
