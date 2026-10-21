import axios from 'axios';

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

// runs just before every request leaves the page, so no service file has to remember to do it
// anything that changes data - like login or register, gets the secret code from PHP added first
// PHP turns those requests away without the code, which stops other websites faking requests as you
api.interceptors.request.use(async (config) => {
    // GET requests go straight through, reading data isn't a security risk, so they don't need the secret code
    // asking for the code is a GET as well, so it doesn't loop round asking for itself
    if (config.method === 'get') {
        return config;
    }

    // only our own page is allowed to read this reply, other websites can't see the code
    const response = await api.get('/csrf-token');
    // the code rides along in a header, PHP checks it against the one saved in the session
    config.headers['X-CSRF-Token'] = response.data.csrfToken;

    return config;
});

export default api;
