import axios from 'axios';

const api = axios.create({
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
});

export async function ensureCsrfCookie() {
    await api.get('/sanctum/csrf-cookie');
}

export function apiErrorMessage(error) {
    if (error?.response?.status === 413) {
        return 'Запрос слишком большой. Уменьшите размер или количество вложений и попробуйте ещё раз.';
    }

    return error?.response?.data?.error?.message
        ?? error?.response?.data?.message
        ?? 'Не удалось выполнить запрос. Проверьте соединение и попробуйте ещё раз.';
}

export function apiFieldErrors(error) {
    return error?.response?.data?.error?.details ?? error?.response?.data?.errors ?? {};
}

export function firstFieldError(errors, field) {
    const message = errors?.[field];
    return Array.isArray(message) ? message[0] : message;
}

export default api;
