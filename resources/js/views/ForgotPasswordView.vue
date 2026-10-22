<template>
    <main class="container auth-container py-5">
        <section class="card border-0 shadow-sm mx-auto">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-2">Восстановление пароля</h1>
                <p class="text-body-secondary mb-4">Укажите e-mail аккаунта. Если он зарегистрирован, мы отправим ссылку для сброса.</p>
                <div v-if="success" class="alert alert-success" role="status">{{ success }}</div>
                <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
                <form novalidate @submit.prevent="submit">
                    <div class="mb-3">
                        <label class="form-label" for="forgot-email">E-mail</label>
                        <input id="forgot-email" v-model.trim="email" class="form-control" type="email" autocomplete="email" required :aria-invalid="Boolean(fieldError)">
                        <div v-if="fieldError" class="invalid-feedback d-block">{{ fieldError }}</div>
                    </div>
                    <button class="btn btn-primary w-100" type="submit" :disabled="submitting">
                        {{ submitting ? 'Отправляем…' : 'Отправить ссылку' }}
                    </button>
                </form>
                <p class="small text-center mt-3 mb-0"><RouterLink to="/login">Вернуться ко входу</RouterLink></p>
            </div>
        </section>
    </main>
</template>

<script setup>
import { ref } from 'vue';
import { requestPasswordReset } from '../api/auth.js';
import { apiErrorMessage, apiFieldErrors, firstFieldError } from '../api/client.js';

const email = ref('');
const submitting = ref(false);
const success = ref('');
const error = ref('');
const fieldError = ref('');

async function submit() {
    success.value = '';
    error.value = '';
    fieldError.value = '';
    if (!email.value) {
        fieldError.value = 'Введите e-mail.';
        return;
    }
    submitting.value = true;
    try {
        const { data } = await requestPasswordReset({ email: email.value });
        success.value = data.message ?? 'Если аккаунт существует, ссылка для сброса отправлена на e-mail.';
    } catch (requestError) {
        fieldError.value = firstFieldError(apiFieldErrors(requestError), 'email') ?? '';
        error.value = apiErrorMessage(requestError);
    } finally {
        submitting.value = false;
    }
}
</script>
