import api from './api';

export async function getDashboardSummary() {
    const response = await api.get('/lecturer/dashboard-summary');
    return response.data;
}

export async function getRecentStudentActivity() {
    const response = await api.get('/lecturer/recent-students');
    return response.data;
}
