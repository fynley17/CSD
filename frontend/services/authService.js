// Auth service, every call the frontend makes to the login backend goes through here
// pages just call login() or getCurrentUser() and don't need to know the backend addresses
import api from './api';

/**
 * Sends the username and password to POST /api/login
 * if they're right, PHP starts a session and the browser keeps the cookie by itself
 * if they're wrong - axios throws an error, the login form catches it and shows PHP's message
 * @returns {Promise<object>} the account type, i.e. { accountType: 'admin' }, used to pick their dashboard
 */
export async function login(username, password) {
    // axios turns this object into JSON before sending it
    const response = await api.post('/login', { username, password });
    return response.data;
}

/**
 * Tells PHP to end the session, used when someone signs out
 */
export async function logout() {
    await api.post('/logout');
}

/**
 * Asks GET /api/current-user who's logged in, router.js uses this before showing a private page
 * @returns {Promise<object|null>} username and account type, or null if nobody is logged in
 */
export async function getCurrentUser() {
    try {
        const response = await api.get('/current-user');
        return response.data;
    } catch (error) {
        // PHP sends 401 when nobody's logged in, that's a normal answer so give back null
        // anything else is an issue, like PHP not running, so pass the error on
        if (error.response?.status === 401) {
            return null;
        }
        throw error;
    }
}
