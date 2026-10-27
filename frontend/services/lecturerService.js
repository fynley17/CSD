import api from './api';

export async function getDashboardSummary() {
    const response = await api.get('/lecturer/dashboard-summary');
    return response.data;
}
