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

/**
 * Gets every lecturer for the lecturer management table from GET /api/admin/lecturers
 * @returns each lecturer with userID, firstName, lastName, name, staffID, email, isEnabled and lastLogin
 */
export async function getLecturers() {
    const response = await api.get('/admin/lecturers');
    return response.data;
}

/**
 * Add Lecturer pop-up, makes the account and PHP emails them a link to set their own password
 * @param details firstName, lastName, staffID, email and role ('lecturer' or 'admin')
 * @returns { message, emailSent, userID }, message says if the email went or not
 */
export async function createLecturer(details) {
    const response = await api.post('/admin/lecturers', details);
    return response.data;
}

/**
 * Resend link button, a fresh set password email for someone who hasn't set one yet
 */
export async function resendSetupLink(userID) {
    const response = await api.post(`/admin/lecturers/${encodeURIComponent(userID)}/setup-link`);
    return response.data;
}

/**
 * Saves the Edit Lecturer pop-up, PHP sends a 400 with the message if something typed isn't allowed
 * @param details firstName, lastName, staffID and email
 */
export async function updateLecturer(userID, details) {
    const response = await api.put(`/admin/lecturers/${encodeURIComponent(userID)}`, details);
    return response.data;
}

/**
 * Disable account and Enable account. false turns it off, true turns it back on (Undo uses this too)
 */
export async function setLecturerEnabled(userID, isEnabled) {
    const response = await api.patch(`/admin/lecturers/${encodeURIComponent(userID)}/enabled`, { isEnabled });
    return response.data;
}

/**
 * Deletes a lecturer, their tests stay and go to the admin who deleted them
 */
export async function deleteLecturer(userID) {
    const response = await api.delete(`/admin/lecturers/${encodeURIComponent(userID)}`);
    return response.data;
}
