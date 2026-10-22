<template>
    <main class="container auth-container py-5">
        <section class="card border-0 shadow-sm mx-auto">
            <div class="card-body p-4 p-md-5">
                <h1 class="h3 mb-2">Профиль</h1>
                <p class="text-body-secondary mb-4">Эти данные отображаются у ваших комментариев.</p>
                <div v-if="loading" class="py-4 text-center" role="status">Загружаем профиль…</div>
                <template v-else>
                    <div v-if="error" class="alert alert-danger" role="alert">{{ error }}</div>
                    <div v-if="success" class="alert alert-success" role="status">{{ success }}</div>
                    <form novalidate @submit.prevent="submit">
                        <div class="mb-3">
                            <label class="form-label" for="profile-name">Имя</label>
                            <input id="profile-name" v-model.trim="form.name" class="form-control" autocomplete="name" maxlength="255" required :aria-invalid="Boolean(fieldErrors.name)">
                            <div v-if="fieldErrors.name" class="invalid-feedback d-block">{{ fieldErrors.name }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profile-email">E-mail</label>
                            <input id="profile-email" v-model.trim="form.email" class="form-control" type="email" autocomplete="email" required :aria-invalid="Boolean(fieldErrors.email)">
                            <div v-if="fieldErrors.email" class="invalid-feedback d-block">{{ fieldErrors.email }}</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="profile-home-page">Домашняя страница <span class="text-body-secondary">(необязательно)</span></label>
                            <input id="profile-home-page" v-model.trim="form.home_page" class="form-control" type="url" autocomplete="url" placeholder="https://example.com" :aria-invalid="Boolean(fieldErrors.home_page)">
                            <div v-if="fieldErrors.home_page" class="invalid-feedback d-block">{{ fieldErrors.home_page }}</div>
                        </div>
                        <button class="btn btn-primary" type="submit" :disabled="submitting">
                            {{ submitting ? 'Сохраняем…' : 'Сохранить профиль' }}
                        </button>
                    </form>
                </template>
            </div>
        </section>
    </main>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { updateProfile } from '../api/profile.js';
import { apiErrorMessage, apiFieldErrors, firstFieldError } from '../api/client.js';
import { useAuthStore } from '../stores/auth.js';

const auth = useAuthStore();
const form = reactive({ name: '', email: '', home_page: '' });
const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const success = ref('');
const fieldErrors = ref({});

onMounted(() => {
    Object.assign(form, {
        name: auth.currentUser?.name ?? '',
        email: auth.currentUser?.email ?? '',
        home_page: auth.currentUser?.home_page ?? '',
    });
    loading.value = false;
});

async function submit() {
    error.value = '';
    success.value = '';
    fieldErrors.value = {};
    if (!form.name || !form.email) {
        error.value = 'Имя и e-mail обязательны.';
        return;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
        fieldErrors.value.email = 'Введите корректный e-mail.';
        return;
    }
    if (form.home_page) {
        try {
            const url = new URL(form.home_page);
            if (!['http:', 'https:'].includes(url.protocol)) throw new Error('Invalid protocol');
        } catch {
            fieldErrors.value.home_page = 'Введите корректный URL, начинающийся с http:// или https://.';
            return;
        }
    }
    submitting.value = true;
    try {
        const user = await updateProfile({ ...form, home_page: form.home_page || null });
        auth.setCurrentUser(user);
        Object.assign(form, { name: user.name, email: user.email, home_page: user.home_page ?? '' });
        success.value = 'Профиль обновлён.';
    } catch (requestError) {
        const errors = apiFieldErrors(requestError);
        fieldErrors.value = Object.fromEntries(['name', 'email', 'home_page'].map((field) => [field, firstFieldError(errors, field)]).filter(([, message]) => message));
        error.value = apiErrorMessage(requestError);
    } finally {
        submitting.value = false;
    }
}
</script>
