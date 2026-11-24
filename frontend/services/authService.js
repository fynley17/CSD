// Auth service, every call the frontend makes to the login backend goes through here
// pages just call login() or getCurrentUser() and don't need to know the backend addresses
import api from './api';
import { startIdleTimer, stopIdleTimer } from './idleTimer';

/**
 * Sends the username and password to POST /api/login
 * if they're right, PHP starts a session and the browser keeps the cookie by itself
 * if they're wrong - axios throws an error, the login form catches it and shows PHP's message
 * @returns {Promise<object>} the account type, i.e. { accountType: 'admin' }, used to pick their dashboard
 * or { mfaRequired, maskedEmail, emailFailed } with MFA on, not logged in yet
 */
export async function login(username, password) {
    // axios turns this object into JSON before sending it
    const response = await api.post('/login', { username, password });
    // logged in, so the 30 minute countdown starts
    // not with MFA on though, that waits for verifyLoginCode()
    if (response.data.accountType) {
        startIdleTimer();
    }
    return response.data;
}

/**
 * Sends the code (or a backup code) to POST /api/login/code
 * @returns {Promise<object>} accountType, plus backupCodes if there's a new set
 */
export async function verifyLoginCode(code) {
    const response = await api.post('/login/code', { code });
    // logged in now, start the countdown
    startIdleTimer();
    return response.data;
}

/**
 * POST /api/login/code/resend, emails a new code
 * @returns {Promise<object>} PHP's message e.g. { message: 'We have sent a new security code' }
 */
export async function resendLoginCode() {
    const response = await api.post('/login/code/resend');
    return response.data;
}

/**
 * POST /api/login/backup-codes once the codes are copied or downloaded, PHP saves them then
 * nothing's saved before this, refresh = new set next login
 */
export async function saveBackupCodes() {
    const response = await api.post('/login/backup-codes');
    return response.data;
}

/**
 * Tells PHP to end the session, used when someone signs out
 */
export async function logout() {
    await api.post('/logout');
    // signed out, so there's nothing left to count down
    stopIdleTimer();
}

/**
 * Asks GET /api/current-user who's logged in, router.js uses this before showing a private page
 * if PHP can't be reached, or takes longer than timeout, axios throws an error and whoever called this deals with it
 * @param {object} [options]
 * @param {number} [options.timeout] milliseconds to wait for PHP, leave it out to wait as long as the browser does
 * @returns {Promise<object|null>} username and account type, or null if nobody is logged in
 */
export async function getCurrentUser({ timeout } = {}) {
    const response = await api.get('/current-user', timeout ? { timeout } : undefined);

    // PHP says loggedIn false when nobody's logged in, the pages treat null as logged out
    if (!response.data.loggedIn) {
        stopIdleTimer();
        return null;
    }

    // still logged in, so the countdown runs, this is also what starts it again after a page refresh
    startIdleTimer();
    return response.data;
}
