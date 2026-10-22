import api from './api';

export async function getAvailableTests() {
    const response = await api.get('/tests');
    return response.data;
}
