import axios from 'axios';
import { restartIdleTimer } from './idleTimer';

// axios sends requests to the backend in the background, so pages can update without reloading
// every service file uses this one setup so they all talk to the backend the same way
const api = axios.create({
    // just '/api' instead of the full backend address, vite.config.js passes it on to PHP
    // keeps the page and PHP on one address, which the login cookie needs
    baseURL: '/api',
    headers: {
        // tells PHP the data we send is JSON
        'Content-Type': 'application/json',
    },
});

// the secret code PHP gave us, kept so it isn't asked for again before every single request
// null means we don't have one yet, or it's out of date and the next request has to fetch a new one
let csrfToken = null;

// runs just before every request leaves the page, so no service file has to remember to do it
// anything that changes data - like login or register, gets the secret code from PHP added first
// PHP turns those requests away without the code, which stops other websites faking requests as you
api.interceptors.request.use(async (config) => {
    // GET requests go straight through, reading data isn't a security risk, so they don't need the secret code
    // asking for the code is a GET as well, so it doesn't loop round asking for itself
    if (config.method === 'get') {
        return config;
    }

    // only asks PHP when there's no code saved, only our own page is allowed to read this reply
    if (csrfToken === null) {
        const response = await api.get('/csrf-token');
        csrfToken = response.data.csrfToken;
    }
    // the code rides along in a header, PHP checks it against the one saved in the session
    config.headers['X-CSRF-Token'] = csrfToken;

    return config;
});

// runs when a reply comes back from PHP, every request starts PHP's 30 minutes again,
// so the countdown on the page starts again as well
// PHP makes a new secret code at login and ends the session at logout, so the saved one is thrown away then
api.interceptors.response.use(
    (response) => {
        restartIdleTimer();
        if (response.config.url === '/login' || response.config.url === '/logout') {
            csrfToken = null;
        }
        return response;
    },
    async (error) => {
        const request = error.config;

        // a 403 on a request that changes data may mean the saved code is out of date, i.e. the session was reset
        // so it fetches a new code and sends the same request once more, a real refusal (i.e. a disabled account)
        // just gets the same 403 back the second time and that goes on to whoever asked
        if (error.response?.status === 403 && request && request.method !== 'get' && !request.hasRetried) {
            request.hasRetried = true;
            csrfToken = null;
            return api(request);
        }

        // anything else, or the second 403, goes back to whoever asked, i.e. the login form shows PHP's message
        throw error;
    },
);

export default api;
