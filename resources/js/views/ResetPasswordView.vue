<template>
    <main class="container auth-container py-5">
        <section class="card border-0 shadow-sm mx-auto">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-2">Новый пароль</h1>
                <p class="text-body-secondary mb-4">Задайте новый пароль для аккаунта.</p>
                <div v-if="success" class="alert alert-success" role="status">
                    {{ success }} <RouterLink to="/login">Перейти ко входу</RouterLink>
                </div>
                <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
                <form v-if="!success" novalidate @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label" for="reset-email">E-mail</label>
                        <input id="reset-email" v-model.trim="form.email" class="form-control" type="email" autocomplete="email" required :aria-invalid="Boolean(fieldErrors.email)">
                        <div v-if="fieldErrors.email" class="invalid-feedback d-block">{{ fieldErrors.email }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="reset-password">Новый пароль</label>
                        <input id="reset-password" v-model="form.password" class="form-control" type="password" autocomplete="new-password" minlength="10" required :aria-invalid="Boolean(fieldErrors.password)">
                        <div class="form-text">Не менее 10 символов, с буквами и цифрами.</div>
                        <div v-if="fieldErrors.password" class="invalid-feedback d-block">{{ fieldErrors.password }}</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="reset-confirmation">Повторите пароль</label>
                        <input id="reset-confirmation" v-model="form.password_confirmation" class="form-control" type="password" autocomplete="new-password" required :aria-invalid="Boolean(fieldErrors.password_confirmation)">
                        <div v-if="fieldErrors.password_confirmation" class="invalid-feedback d-block">{{ fieldErrors.password_confirmation }}</div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit" :disabled="submitting">
                        {{ submitting ? 'Сохраняем…' : 'Сохранить пароль' }}
                    </button>
                </form>
            </div>
        </section>
    </main>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRoute } from 'vue-router';
import { resetPassword } from '../api/auth.js';
import { apiErrorMessage, apiFieldErrors, firstFieldError } from '../api/client.js';

const route = useRoute();
const form = reactive({ email: typeof route.query.email === 'string' ? route.query.email : '', password: '', password_confirmation: '' });
const submitting = ref(false);
const success = ref('');
const error = ref('');
const fieldErrors = ref({});

async function submit() {
    success.value = '';
    error.value = '';
    fieldErrors.value = {};
    if (!form.email || !form.password || !form.password_confirmation) {
        error.value = 'Заполните все поля.';
        return;
    }
    if (form.password.length < 10 || !/\p{L}/u.test(form.password) || !/\d/.test(form.password)) {
        fieldErrors.value.password = 'Пароль должен содержать не менее 10 символов, включая буквы и цифры.';
        return;
    }
    if (form.password !== form.password_confirmation) {
        fieldErrors.value.password_confirmation = 'Пароли не совпадают.';
        return;
    }
    submitting.value = true;
    try {
        const { data } = await resetPassword({ ...form, token: route.params.token });
        success.value = data.message ?? 'Пароль обновлён.';
    } catch (requestError) {
        const errors = apiFieldErrors(requestError);
        fieldErrors.value = Object.fromEntries(['email', 'password', 'token'].map((field) => [field, firstFieldError(errors, field)]).filter(([, message]) => message));
        error.value = apiErrorMessage(requestError);
    } finally {
        submitting.value = false;
    }
}
</script>
