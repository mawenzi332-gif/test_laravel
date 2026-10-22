<template>
    <main class="container auth-container py-5">
        <section class="card border-0 shadow-sm mx-auto">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-2">Регистрация</h1>
                <p class="text-body-secondary mb-4">Создайте аккаунт для управления профилем и комментариями.</p>
                <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
                <form novalidate @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label" for="register-name">Имя</label>
                        <input id="register-name" v-model.trim="form.name" class="form-control" autocomplete="name" maxlength="255" required :aria-invalid="Boolean(fieldErrors.name)">
                        <div v-if="fieldErrors.name" class="invalid-feedback d-block">{{ fieldErrors.name }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="register-email">E-mail</label>
                        <input id="register-email" v-model.trim="form.email" class="form-control" type="email" autocomplete="email" required :aria-invalid="Boolean(fieldErrors.email)">
                        <div v-if="fieldErrors.email" class="invalid-feedback d-block">{{ fieldErrors.email }}</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="register-password">Пароль</label>
                        <input id="register-password" v-model="form.password" class="form-control" type="password" autocomplete="new-password" minlength="10" required :aria-invalid="Boolean(fieldErrors.password)">
                        <div class="form-text">Не менее 10 символов, с буквами и цифрами.</div>
                        <div v-if="fieldErrors.password" class="invalid-feedback d-block">{{ fieldErrors.password }}</div>
                    </div>
                    <div class="mb-4">
                        <label class="form-label" for="register-confirmation">Повторите пароль</label>
                        <input id="register-confirmation" v-model="form.password_confirmation" class="form-control" type="password" autocomplete="new-password" required :aria-invalid="Boolean(fieldErrors.password_confirmation)">
                        <div v-if="fieldErrors.password_confirmation" class="invalid-feedback d-block">{{ fieldErrors.password_confirmation }}</div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit" :disabled="submitting">
                        {{ submitting ? 'Создаём аккаунт…' : 'Создать аккаунт' }}
                    </button>
                </form>
                <p class="small text-center mt-3 mb-0">Уже зарегистрированы? <RouterLink to="/login">Войти</RouterLink></p>
            </div>
        </section>
    </main>
</template>

<script setup>
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiErrorMessage, apiFieldErrors, firstFieldError } from '../api/client.js';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const router = useRouter();
const form = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const submitting = ref(false);
const error = ref('');
const fieldErrors = ref({});

async function submit() {
    error.value = '';
    fieldErrors.value = {};
    if (!form.name || !form.email || !form.password || !form.password_confirmation) {
        error.value = 'Заполните все обязательные поля.';
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        fieldErrors.value.email = 'Введите корректный e-mail.';
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
        await auth.register(form);
        await router.replace('/');
    } catch (requestError) {
        const errors = apiFieldErrors(requestError);
        fieldErrors.value = Object.fromEntries(['name', 'email', 'password', 'password_confirmation'].map((field) => [field, firstFieldError(errors, field)]).filter(([, message]) => message));
        error.value = apiErrorMessage(requestError);
    } finally {
        submitting.value = false;
    }
}
</script>
