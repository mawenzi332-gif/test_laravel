<template>
    <main class="container auth-container py-5">
        <section class="card border-0 shadow-sm mx-auto">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-2">Вход</h1>
                <p class="text-body-secondary mb-4">Войдите, чтобы управлять профилем и своими комментариями.</p>
                <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
                <form novalidate @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label" for="login-email">E-mail</label>
                        <input id="login-email" v-model.trim="form.email" class="form-control" type="email" autocomplete="email" required :aria-invalid="Boolean(fieldErrors.email)">
                        <div v-if="fieldErrors.email" class="invalid-feedback d-block">{{ fieldErrors.email }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="login-password">Пароль</label>
                        <input id="login-password" v-model="form.password" class="form-control" type="password" autocomplete="current-password" required :aria-invalid="Boolean(fieldErrors.password)">
                        <div v-if="fieldErrors.password" class="invalid-feedback d-block">{{ fieldErrors.password }}</div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit" :disabled="submitting">
                        {{ submitting ? 'Входим…' : 'Войти' }}
                    </button>
                </form>
                <div class="d-flex justify-content-between flex-wrap gap-2 mt-3 small">
                    <RouterLink to="/forgot-password">Забыли пароль?</RouterLink>
                    <RouterLink to="/register">Создать аккаунт</RouterLink>
                </div>
            </div>
        </section>
    </main>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiErrorMessage, apiFieldErrors, firstFieldError } from '../api/client.js';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();
const form = reactive({ email: '', password: '' });
const submitting = ref(false);
const error = ref('');
const fieldErrors = ref({});

async function submit() {
    error.value = '';
    fieldErrors.value = {};
    if (!form.email || !form.password) {
        error.value = 'Введите e-mail и пароль.';
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        fieldErrors.value.email = 'Введите корректный e-mail.';
        return;
    }

    submitting.value = true;
    try {
        await auth.login(form);
        const redirect = typeof route.query.redirect === 'string' ? route.query.redirect : '/';
        await router.replace(redirect.startsWith('/') && !redirect.startsWith('//') ? redirect : '/');
    } catch (requestError) {
        const errors = apiFieldErrors(requestError);
        fieldErrors.value = Object.fromEntries(['email', 'password'].map((field) => [field, firstFieldError(errors, field)]).filter(([, message]) => message));
        error.value = apiErrorMessage(requestError);
    } finally {
        submitting.value = false;
    }
}
</script>
