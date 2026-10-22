import api, { ensureCsrfCookie } from './client.js';

export async function register(payload) {
    await ensureCsrfCookie();
    return api.post('/api/v1/auth/register', payload);
}

export async function login(payload) {
    await ensureCsrfCookie();
    return api.post('/api/v1/auth/login', payload);
}

export async function logout() {
    await ensureCsrfCookie();
    return api.post('/api/v1/auth/logout');
}

export async function requestPasswordReset(payload) {
    await ensureCsrfCookie();
    return api.post('/api/v1/auth/forgot-password', payload);
}

export async function resetPassword(payload) {
    await ensureCsrfCookie();
    return api.post('/api/v1/auth/reset-password', payload);
}
