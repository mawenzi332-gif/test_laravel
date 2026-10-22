import { defineStore } from 'pinia';
import * as authApi from '../api/auth.js';
import { getCurrentUser } from '../api/profile.js';

let initializationRequest;

export const useAuthStore = defineStore('auth', {
    state: () => ({
        currentUser: null,
        authenticated: false,
        initializing: true,
    }),

    actions: {
        async initialize() {
            if (!this.initializing) return;
            if (initializationRequest) return initializationRequest;

            this.initializing = true;
            initializationRequest = (async () => {
                try {
                    this.currentUser = await getCurrentUser();
                    this.authenticated = true;
                } catch (error) {
                    if (error?.response?.status === 401) {
                        this.currentUser = null;
                        this.authenticated = false;
                    } else {
                        throw error;
                    }
                } finally {
                    this.initializing = false;
                    initializationRequest = undefined;
                }
            })();
            return initializationRequest;
        },

        async refreshCurrentUser() {
            this.currentUser = await getCurrentUser();
            this.authenticated = true;
            this.initializing = false;
            return this.currentUser;
        },

        async login(payload) {
            await authApi.login(payload);
            return this.refreshCurrentUser();
        },

        async register(payload) {
            await authApi.register(payload);
            return this.refreshCurrentUser();
        },

        async logout() {
            await authApi.logout();
            this.currentUser = null;
            this.authenticated = false;
            this.initializing = false;
        },

        setCurrentUser(user) {
            this.currentUser = user;
            this.authenticated = Boolean(user);
            this.initializing = false;
        },
    },
});
