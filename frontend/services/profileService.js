// Profile service (CTS-203), every call the My profile page makes goes through here
// PHP works out whose profile it is from the login cookie, so no user ID is ever sent
import api from './api';

/**
 * Asks GET /api/profile for the logged in user's own details
 * @returns {Promise<object>} i.e. { username, accountType, firstName, lastName, email, pendingEmail, cohortName }
 */
export async function getProfile() {
    const response = await api.get('/profile');
    return response.data;
}

/**
 * Saves the names, and asks for a confirm link if the email is different, PUT /api/profile
 * @returns {Promise<object>} PHP's message and the updated details, i.e. { message: '...', profile: {...} }
 */
export async function updateProfile(firstName, lastName, email) {
    const response = await api.put('/profile', { firstName, lastName, email });
    return response.data;
}

/**
 * Changes the password, the current one has to be right, PUT /api/profile/password
 * @returns {Promise<{message: string}>}
 */
export async function changePassword(currentPassword, newPassword, confirmPassword) {
    const response = await api.put('/profile/password', { currentPassword, newPassword, confirmPassword });
    return response.data;
}
