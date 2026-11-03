import api from './api';

// Student test functionality

export async function getAvailableTests() {
    const response = await api.get('/tests');
    return response.data;
}

// Starts an attempt. The backend draws questions at random from the test pool.
export async function startTestAttempt(testID) {
    const response = await api.post(`/tests/${encodeURIComponent(testID)}/start`);
    return response.data;
}

// Submit answers and receive the marked result.
export async function submitTest(testID, answers) {
    const response = await api.post(`/tests/${encodeURIComponent(testID)}/complete`, { answers });
    return response.data;
}

// Lecturer test management functionality

// Retrieve all tests for the management page.
export async function getLecturerTests() {
    const response = await api.get('/lecturer/tests');
    return response.data;
}

// Retrieve active subjects for the create/edit test form.
export async function getActiveTestSubjects() {
    const response = await api.get('/lecturer/test-subjects');
    return response.data;
}

// Create a new test.
export async function createTest(testData) {
    const response = await api.post('/lecturer/tests', testData);
    return response.data;
}

// Update an existing test.
export async function updateTest(testID, testData) {
    const response = await api.put(`/lecturer/tests/${encodeURIComponent(testID)}`, testData);
    return response.data;
}

// Delete a test.
export async function deleteTest(testID) {
    const response = await api.delete(`/lecturer/tests/${encodeURIComponent(testID)}`);
    return response.data;
}
