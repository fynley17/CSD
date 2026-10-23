// Verification service, every call the check your email and verify email pages make goes through here
// pages just call these functions and don't need to know the backend addresses
import api from './api';

/**
 * Asks GET /api/pending-verification which address the link was sent to
 * PHP knows which account from the session cookie set when they registered or tried to login
 * @returns {Promise<object>} the address and whether it's verified, i.e. { email: '...', isEmailVerified: false }
 */
export async function getPendingVerification() {
    const response = await api.get('/pending-verification');
    return response.data;
}

/**
 * Asks POST /api/resend-verification to send a new link to the same address, the old link stops working
 * @returns {Promise<object>} PHP's message, i.e. { message: 'We have sent a new link to ...' }
 */
export async function resendVerification() {
    const response = await api.post('/resend-verification');
    return response.data;
}

/**
 * Sends a corrected address to POST /api/change-email, PHP saves it and sends a new link there
 * @returns {Promise<object>} PHP's message and the saved address, i.e. { message: '...', email: '...' }
 */
export async function changeEmail(email) {
    const response = await api.post('/change-email', { email });
    return response.data;
}

/**
 * Sends the code from the email link to POST /api/verify-email
 * the code goes in the body rather than the URL, so it doesn't end up in the server logs
 * @returns {Promise<object>} PHP's message, i.e. { message: 'Your email address has been verified' }
 */
export async function verifyEmail(token) {
    const response = await api.post('/verify-email', { token });
    return response.data;
}
