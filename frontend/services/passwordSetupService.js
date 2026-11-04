import api from './api';

export async function setPassword(token, password, confirmPassword) {
    const response = await api.post('/setup-password', {
        token,
        password,
        confirmPassword,
    });

    return response.data;
}
