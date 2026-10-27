import api from './api';

export async function getCompletedTests() {
    const response = await api.get('/tests/completed');
    return response.data;
}
