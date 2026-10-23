<template>
    <div class="app-shell">
        <header class="border-bottom bg-white">
            <nav class="container navbar navbar-expand-sm py-3" aria-label="Основная навигация">
                <RouterLink class="navbar-brand fw-semibold" to="/">Комментарии</RouterLink>
                <div class="ms-auto d-flex align-items-center gap-3">
                    <template v-if="auth.authenticated">
                        <span class="small text-body-secondary d-none d-sm-inline">{{ auth.currentUser?.name }}</span>
                        <RouterLink class="btn btn-outline-primary btn-sm" to="/profile">Профиль</RouterLink>
                        <button class="btn btn-link btn-sm text-decoration-none" :disabled="loggingOut" @click="onLogout">
                            {{ loggingOut ? 'Выходим…' : 'Выйти' }}
                        </button>
                    </template>
                    <template v-else-if="!auth.initializing">
                        <RouterLink class="btn btn-outline-primary btn-sm" to="/login">Войти</RouterLink>
                        <RouterLink class="btn btn-primary btn-sm" to="/register">Регистрация</RouterLink>
                    </template>
                </div>
            </nav>
            <div v-if="logoutError" class="container pb-2 small text-danger" role="alert">{{ logoutError }}</div>
        </header>
        <FeedbackToast />
        <router-view />
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth.js';
import FeedbackToast from './components/FeedbackToast.vue';

const auth = useAuthStore();
const router = useRouter();
const loggingOut = ref(false);
const logoutError = ref('');

async function onLogout() {
    logoutError.value = '';
    loggingOut.value = true;
    try {
        await auth.logout();
        await router.push('/');
    } catch {
        logoutError.value = 'Не удалось завершить сеанс. Попробуйте ещё раз.';
    } finally {
        loggingOut.value = false;
    }
}
</script>
