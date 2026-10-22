import api, { ensureCsrfCookie } from './client.js';

export async function listComments(params) {
    const { data } = await api.get('/api/v1/comments', { params });
    return data;
}

export async function previewComment(text) {
    await ensureCsrfCookie();
    const { data } = await api.post('/api/v1/comments/preview', { text });
    return data.data.body_html;
}

export async function createComment(payload) {
    await ensureCsrfCookie();
    const { data } = await api.post('/api/v1/comments', toFormData(payload));
    return data.data;
}

export async function updateComment(id, payload) {
    await ensureCsrfCookie();
    const form = toFormData(payload);
    form.append('_method', 'PATCH');
    const { data } = await api.post(`/api/v1/comments/${id}`, form);
    return data.data;
}

export async function deleteComment(id) {
    await ensureCsrfCookie();
    await api.delete(`/api/v1/comments/${id}`);
}

function toFormData(payload) {
    const form = new FormData();
    Object.entries(payload).forEach(([key, value]) => {
        if (Array.isArray(value)) {
            if (key === 'attachments_to_keep' && value.length === 0) {
                form.append('_attachments_to_keep_empty', '1');
            }
            value.forEach((item) => form.append(`${key}[]`, item));
        } else if (value !== undefined && value !== null) {
            form.append(key, value);
        }
    });
    return form;
}
