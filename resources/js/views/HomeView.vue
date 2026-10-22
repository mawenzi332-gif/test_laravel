<template>
    <main class="container py-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h3 mb-2">Комментарии</h1>
                <p class="text-body-secondary mb-0">
                    {{ loading ? 'Загружаем комментарии…' : `Комментариев на этой странице: ${comments.length}` }}
                </p>
            </div>
        </div>
    </main>
</template>

<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const comments = ref([]);
const loading = ref(true);
const page = ref(1);
let echo;
let refreshTimer;

async function refreshComments() {
    const { data } = await axios.get('/api/v1/comments', { params: { page: page.value } });
    comments.value = data.data;
}

function scheduleRefresh() {
    window.clearTimeout(refreshTimer);
    refreshTimer = window.setTimeout(() => {
        refreshComments().catch(() => {});
    }, 250);
}

onMounted(async () => {
    try {
        await refreshComments();
    } catch {
        comments.value = [];
    } finally {
        loading.value = false;
    }

    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8081),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8081),
        forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    echo.channel('comments').listen('.comment.created', scheduleRefresh);
});

onUnmounted(() => {
    window.clearTimeout(refreshTimer);
    echo?.leave('comments');
    echo?.disconnect();
});
</script>
