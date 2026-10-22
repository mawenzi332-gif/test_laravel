import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth.js';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'home',
            component: () => import('../views/HomeView.vue'),
        },
        { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { guestOnly: true } },
        { path: '/register', name: 'register', component: () => import('../views/RegisterView.vue'), meta: { guestOnly: true } },
        { path: '/forgot-password', name: 'forgot-password', component: () => import('../views/ForgotPasswordView.vue'), meta: { guestOnly: true } },
        { path: '/reset-password/:token', name: 'reset-password', component: () => import('../views/ResetPasswordView.vue'), meta: { guestOnly: true } },
        { path: '/profile', name: 'profile', component: () => import('../views/ProfileView.vue'), meta: { requiresAuth: true } },
    ],
});

router.beforeEach(async (to) => {
    const auth = useAuthStore();
    try {
        await auth.initialize();
    } catch {
        return { name: 'home' };
    }

    if (to.meta.requiresAuth && !auth.authenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }

    if (to.meta.guestOnly && auth.authenticated) {
        return { name: 'home' };
    }
});

export default router;
