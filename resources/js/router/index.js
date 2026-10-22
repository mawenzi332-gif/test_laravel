import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import LoginView from '../views/LoginView.vue';
import RegisterView from '../views/RegisterView.vue';
import ForgotPasswordView from '../views/ForgotPasswordView.vue';
import ResetPasswordView from '../views/ResetPasswordView.vue';
import ProfileView from '../views/ProfileView.vue';
import { useAuthStore } from '../stores/auth.js';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            name: 'home',
            component: HomeView,
        },
        { path: '/login', name: 'login', component: LoginView, meta: { guestOnly: true } },
        { path: '/register', name: 'register', component: RegisterView, meta: { guestOnly: true } },
        { path: '/forgot-password', name: 'forgot-password', component: ForgotPasswordView, meta: { guestOnly: true } },
        { path: '/reset-password/:token', name: 'reset-password', component: ResetPasswordView, meta: { guestOnly: true } },
        { path: '/profile', name: 'profile', component: ProfileView, meta: { requiresAuth: true } },
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
