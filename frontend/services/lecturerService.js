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

// Fetch active subjects for lecturer forms.
export async function getLecturerSubjects() {
    const response = await api.get('/lecturer/subjects');
    return response.data;
}

// Create a new test.
export async function createLecturerTest(testData) {
    const response = await api.post('/lecturer/tests', testData);
    return response.data;
}

// Update an existing test.
export async function updateLecturerTest(testID, testData) {
    const response = await api.put(`/lecturer/tests/${encodeURIComponent(testID)}`, testData);
    return response.data;
}

// Delete an existing test.
export async function deleteLecturerTest(testID) {
    const response = await api.delete(`/lecturer/tests/${encodeURIComponent(testID)}`);
    return response.data;
}
