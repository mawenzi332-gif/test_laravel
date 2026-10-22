<template>
    <main class="container comments-page py-4 py-lg-5">
        <div class="comments-heading card border-0 shadow-sm mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
                    <div><h1 class="h2 mb-1">Комментарии</h1><p class="text-body-secondary mb-0">Обсуждение с ответами, форматированием и вложениями</p></div>
                    <button type="button" class="btn btn-primary align-self-start" @click="showNewComment = !showNewComment">{{ showNewComment ? 'Закрыть форму' : 'Добавить комментарий' }}</button>
                </div>
                <div class="comments-controls mt-4">
                    <label class="form-label mb-0" for="comment-sort">Сортировать по</label>
                    <select id="comment-sort" v-model="sortBy" class="form-select form-select-sm" @change="changeSort">
                        <option value="date">Дате</option><option value="name">Имени</option><option value="email">E-mail</option>
                    </select>
                    <select v-model="direction" class="form-select form-select-sm" aria-label="Направление сортировки" @change="changeSort">
                        <option value="desc">По убыванию</option><option value="asc">По возрастанию</option>
                    </select>
                    <span v-if="meta.total" class="small text-body-secondary ms-md-auto">{{ meta.total }} комментариев · страница {{ meta.current_page }} из {{ meta.last_page }}</span>
                </div>
            </div>
        </div>

        <CommentComposer v-if="showNewComment" class="mb-4" @saved="onPublished" />

        <div v-if="loading" class="card card-body text-center py-5" role="status">Загружаем комментарии…</div>
        <div v-else-if="loadError" class="alert alert-danger d-flex flex-wrap justify-content-between align-items-center gap-2" role="alert"><span>{{ loadError }}</span><button type="button" class="btn btn-sm btn-outline-danger" @click="loadComments">Повторить</button></div>
        <div v-else-if="comments.length === 0" class="card card-body text-center py-5"><h2 class="h5">Пока нет комментариев</h2><p class="text-body-secondary mb-0">Начните обсуждение — ваш комментарий появится здесь.</p></div>
        <section v-else class="comment-list" aria-label="Список комментариев">
            <CommentNode v-for="comment in comments" :key="comment.id" :comment="comment" @changed="loadComments" />
        </section>

        <nav v-if="!loading && !loadError && meta.last_page > 1" class="mt-4" aria-label="Страницы комментариев">
            <ul class="pagination justify-content-center flex-wrap">
                <li class="page-item" :class="{ disabled: meta.current_page <= 1 }"><button class="page-link" :disabled="meta.current_page <= 1" @click="goTo(meta.current_page - 1)">Назад</button></li>
                <li v-for="number in pageNumbers" :key="number" class="page-item" :class="{ active: number === meta.current_page }"><button class="page-link" :aria-current="number === meta.current_page ? 'page' : undefined" @click="goTo(number)">{{ number }}</button></li>
                <li class="page-item" :class="{ disabled: meta.current_page >= meta.last_page }"><button class="page-link" :disabled="meta.current_page >= meta.last_page" @click="goTo(meta.current_page + 1)">Вперёд</button></li>
            </ul>
        </nav>
    </main>
</template>

<script setup>
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { listComments } from '../api/comments.js';
import { apiErrorMessage } from '../api/client.js';
import CommentNode from '../components/CommentNode.vue';

window.Pusher = Pusher;
const CommentComposer = defineAsyncComponent(() => import('../components/CommentComposer.vue'));
const comments = ref([]);
const loading = ref(true);
const loadError = ref('');
const showNewComment = ref(false);
const sortBy = ref('date');
const direction = ref('desc');
const page = ref(1);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const pageNumbers = computed(() => {
    const first = Math.max(1, meta.value.current_page - 2);
    const last = Math.min(meta.value.last_page, first + 4);
    return Array.from({ length: last - first + 1 }, (_, index) => first + index);
});
let echo;
let refreshTimer;
let requestVersion = 0;

async function loadComments() {
    const version = ++requestVersion;
    loading.value = true;
    loadError.value = '';
    try {
        const response = await listComments({ page: page.value, sort_by: sortBy.value, direction: direction.value });
        if (version !== requestVersion) return;
        comments.value = response.data;
        meta.value = response.meta;
    } catch (error) {
        if (version !== requestVersion) return;
        comments.value = [];
        loadError.value = apiErrorMessage(error);
    } finally { if (version === requestVersion) loading.value = false; }
}

function changeSort() { page.value = 1; loadComments(); }
function goTo(number) { if (number >= 1 && number <= meta.value.last_page) { page.value = number; loadComments(); window.scrollTo({ top: 0, behavior: 'smooth' }); } }
function onPublished() { showNewComment.value = false; sortBy.value = 'date'; direction.value = 'desc'; page.value = 1; loadComments(); }
function scheduleRefresh() { window.clearTimeout(refreshTimer); refreshTimer = window.setTimeout(() => loadComments(), 300); }

onMounted(async () => {
    await loadComments();
    if (!import.meta.env.VITE_REVERB_APP_KEY) return;
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

onUnmounted(() => { window.clearTimeout(refreshTimer); echo?.leave('comments'); echo?.disconnect(); });
</script>
