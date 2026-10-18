import axios from 'axios';

// Share the API base URL and request configuration across registration calls.
const api = axios.create({
    baseURL: 'http://localhost:8000/api', // Adjust as needed
    headers: {
        'Content-Type': 'application/json',
    },
});

api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

export default api;
