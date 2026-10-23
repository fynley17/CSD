// Auth service, every call the frontend makes to the login backend goes through here
// pages just call login() or getCurrentUser() and don't need to know the backend addresses
import api from './api';
import { startIdleTimer, stopIdleTimer } from './idleTimer';

/**
 * Sends the username and password to POST /api/login
 * if they're right, PHP starts a session and the browser keeps the cookie by itself
 * if they're wrong - axios throws an error, the login form catches it and shows PHP's message
 * @returns {Promise<object>} the account type, i.e. { accountType: 'admin' }, used to pick their dashboard
 */
export async function login(username, password) {
    // axios turns this object into JSON before sending it
    const response = await api.post('/login', { username, password });
    // logged in, so the 30 minute countdown starts
    startIdleTimer();
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
 * if PHP can't be reached axios throws an error, and whoever called this deals with it
 * @returns {Promise<object|null>} username and account type, or null if nobody is logged in
 */
export async function getCurrentUser() {
    const response = await api.get('/current-user');

    // PHP says loggedIn false when nobody's logged in, the pages treat null as logged out
    if (!response.data.loggedIn) {
        stopIdleTimer();
        return null;
    }

    // still logged in, so the countdown runs, this is also what starts it again after a page refresh
    startIdleTimer();
    return response.data;
}
