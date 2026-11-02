// asks PHP for what the admin pages show, every request goes through api.js
// so it uses the same address and login cookie
import api from './api';

/**
 * Gets the four totals for the admin dashboard cards from GET /api/admin/dashboard-summary
 * @returns the totals, i.e. { totalSubjects: 8, totalLecturers: 12, archivedSubjects: 2, disabledLecturers: 1 }
 */
export async function getDashboardSummary() {
    const response = await api.get('/admin/dashboard-summary');
    return response.data;
}

/**
 * Gets the lecturers for the admin dashboard table from GET /api/admin/recent-lecturers
 * @returns up to 5 lecturers, latest login first, i.e. [{ name, staffID, email, isEnabled, lastLogin }]
 */
export async function getRecentLecturers() {
    const response = await api.get('/admin/recent-lecturers');
    return response.data;
}
