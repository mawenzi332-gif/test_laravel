import api, { ensureCsrfCookie } from './client.js';

export async function getCurrentUser() {
    const { data } = await api.get('/api/v1/me');
    return data.data;
}

export async function updateProfile(payload) {
    await ensureCsrfCookie();
    const { data } = await api.patch('/api/v1/me', payload);
    return data.data;
}
