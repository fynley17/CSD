// asks PHP for what the admin pages show, every request goes through api.js so it reaches the backend the same way
import api from './api';

/**
 * Gets the four totals for the admin dashboard cards from GET /api/admin/dashboard-summary
 * @returns the totals, i.e. { totalSubjects: 8, totalLecturers: 12, archivedSubjects: 2, disabledLecturers: 1 }
 */
export async function getDashboardSummary() {
    const response = await api.get('/admin/dashboard-summary');
    return response.data;
}
