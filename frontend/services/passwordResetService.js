import api from './api';

/**
 * Asks PHP to email a reset link, POST /api/forgot-password
 * PHP gives the same reply whether or not the email has an account
 * @returns {Promise<{message: string}>}
 */
export async function requestPasswordReset(email) {
    const response = await api.post('/forgot-password', { email });
    return response.data;
}

/**
 * Sets the new password using the code from the email link, POST /api/reset-password
 * @returns {Promise<{message: string}>}
 */
export async function resetPassword(token, password, confirmPassword) {
    const response = await api.post('/reset-password', { token, password, confirmPassword });
    return response.data;
}
