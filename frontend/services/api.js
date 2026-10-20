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

export default api;
