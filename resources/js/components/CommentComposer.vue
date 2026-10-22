<template>
    <form class="comment-composer card card-body" @submit.prevent="submit">
        <div class="d-flex align-items-start justify-content-between gap-3 mb-3">
            <div>
                <h3 class="h5 mb-1">{{ heading }}</h3>
                <p v-if="parentAuthor" class="small text-body-secondary mb-0">Ответ для {{ parentAuthor }}</p>
                <p v-else-if="auth.authenticated" class="small text-body-secondary mb-0">Публикация от имени {{ auth.currentUser?.name }}</p>
            </div>
            <button v-if="dismissible" type="button" class="btn-close" aria-label="Закрыть форму" @click="$emit('cancel')"></button>
        </div>

        <div v-if="auth.authenticated && mode === 'create'" class="alert alert-light border py-2 small">
            Автор: <strong>{{ auth.currentUser?.name }}</strong> · {{ auth.currentUser?.email }}<span v-if="auth.currentUser?.home_page"> · {{ auth.currentUser.home_page }}</span>
        </div>
        <div v-else-if="!auth.authenticated && mode === 'create'" class="row g-3 mb-3">
            <div class="col-md-6"><label class="form-label" :for="`${formId}-name`">Имя</label><input :id="`${formId}-name`" v-model.trim="guest.name" class="form-control" autocomplete="name" required maxlength="255"><FieldError :message="fieldError('guest_name')" /></div>
            <div class="col-md-6"><label class="form-label" :for="`${formId}-email`">E-mail</label><input :id="`${formId}-email`" v-model.trim="guest.email" class="form-control" type="email" autocomplete="email" required maxlength="255"><FieldError :message="fieldError('guest_email')" /></div>
            <div class="col-12"><label class="form-label" :for="`${formId}-homepage`">Домашняя страница <span class="text-body-secondary">(необязательно)</span></label><input :id="`${formId}-homepage`" v-model.trim="guest.homePage" class="form-control" type="url" autocomplete="url" placeholder="https://example.com" maxlength="2048"><FieldError :message="fieldError('guest_home_page')" /></div>
        </div>

        <div class="mb-3">
            <label class="form-label" :for="`${formId}-editor`">Текст комментария</label>
            <CommentEditor :id="`${formId}-editor`" v-model="text" :disabled="busy" />
            <FieldError :message="fieldError('text')" />
        </div>

        <div v-if="mode === 'create' || canChangeAttachments" class="mb-3">
            <div v-if="mode === 'edit' && savedAttachments.length" class="mb-3">
                <div class="form-label">Опубликованные вложения</div>
                <div class="saved-attachment-list">
                    <div v-for="file in savedAttachments" :key="file.id" class="saved-attachment-row">
                        <img v-if="file.preview_url" :src="file.preview_url" :alt="file.name" class="draft-thumb">
                        <span v-else class="draft-file-icon" aria-hidden="true">TXT</span>
                        <span class="text-truncate flex-grow-1">{{ file.file_name ?? file.name }}</span>
                        <a v-if="file.mime_type === 'text/plain'" class="btn btn-sm btn-outline-secondary" :href="file.url" target="_blank" rel="noopener">Скачать</a>
                        <button type="button" class="btn btn-sm" :class="keptIds.includes(file.id) ? 'btn-outline-danger' : 'btn-outline-success'" @click="toggleKeep(file.id)">{{ keptIds.includes(file.id) ? 'Убрать' : 'Оставить' }}</button>
                    </div>
                </div>
            </div>
            <AttachmentPicker v-model="files" :image-limit="imageLimit" :max-attachments="Math.max(0, 3 - savedAttachments.length)" :input-id="`${formId}-files`" />
            <ul v-if="attachmentErrors.length" class="list-unstyled mt-2 mb-0 small text-danger" role="alert"><li v-for="message in attachmentErrors" :key="message">{{ message }}</li></ul>
        </div>

        <div v-if="mode === 'create'" class="mb-3">
            <label class="form-label">Проверка CAPTCHA</label>
            <GoogleCaptcha ref="captcha" v-model="captchaToken" />
            <FieldError :message="fieldError('g-recaptcha-response') || fieldError('captcha')" />
        </div>

        <div v-if="previewOpen" class="preview-panel border rounded p-3 mb-3" aria-live="polite">
            <div class="d-flex justify-content-between align-items-center mb-2"><h4 class="h6 mb-0">Предварительный просмотр</h4><button type="button" class="btn-close" aria-label="Закрыть предпросмотр" @click="previewOpen = false"></button></div>
            <div v-if="previewHtml" class="comment-body" v-html="previewHtml"></div>
            <p v-else class="small text-body-secondary">Добавьте текст, чтобы увидеть предварительный просмотр.</p>
            <AttachmentDisplay :attachments="previewAttachments" :draft-files="files" @image="openDraftImage" />
        </div>

        <div v-if="formError" class="alert alert-danger py-2" role="alert">{{ formError }}</div>
        <div class="d-flex flex-wrap justify-content-end gap-2">
            <button type="button" class="btn btn-outline-secondary" :disabled="busy || previewing" @click="togglePreview">{{ previewing ? 'Готовим…' : previewOpen ? 'Обновить просмотр' : 'Предпросмотр' }}</button>
            <button type="submit" class="btn btn-primary" :disabled="busy || !hasText || (mode === 'create' && !captchaToken)">{{ busy ? 'Сохраняем…' : submitLabel }}</button>
        </div>
    </form>
    <ImageViewer v-if="draftImage" :src="draftImage.src" :alt="draftImage.alt" @close="draftImage = null" />
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { apiErrorMessage, apiFieldErrors } from '../api/client.js';
import { createComment, previewComment, updateComment } from '../api/comments.js';
import { useAuthStore } from '../stores/auth.js';
import AttachmentDisplay from './AttachmentDisplay.vue';
import AttachmentPicker from './AttachmentPicker.vue';
import CommentEditor from './CommentEditor.vue';
import FieldError from './FieldError.vue';
import GoogleCaptcha from './GoogleCaptcha.vue';
import ImageViewer from './ImageViewer.vue';

const props = defineProps({ mode: { type: String, default: 'create' }, parentId: { type: Number, default: null }, parentAuthor: { type: String, default: '' }, comment: { type: Object, default: null }, dismissible: Boolean, imageLimit: { type: Number, default: Number(import.meta.env.VITE_MEDIA_IMAGE_MAX_MB ?? 10) } });
const emit = defineEmits(['saved', 'cancel']);
const auth = useAuthStore();
const text = ref(props.comment?.body_html ?? '');
const guest = ref({ name: '', email: '', homePage: '' });
const files = ref([]);
const keptIds = ref(props.comment?.attachments?.map((file) => file.id) ?? []);
const captchaToken = ref('');
const captcha = ref(null);
const busy = ref(false);
const previewing = ref(false);
const previewOpen = ref(false);
const previewHtml = ref('');
const formError = ref('');
const fieldErrors = ref({});
const draftImage = ref(null);
const formId = `comment-form-${Math.random().toString(36).slice(2, 9)}`;
const savedAttachments = computed(() => (props.comment?.attachments ?? []).filter((file) => keptIds.value.includes(file.id)));
const attachmentErrors = computed(() => Object.entries(fieldErrors.value)
    .filter(([field]) => field === 'attachments' || field.startsWith('attachments.'))
    .flatMap(([, messages]) => Array.isArray(messages) ? messages : [messages]));
const hasText = computed(() => new DOMParser().parseFromString(text.value, 'text/html').body.textContent.trim().length > 0);
const canChangeAttachments = computed(() => props.mode === 'edit');
const heading = computed(() => props.mode === 'edit' ? 'Редактировать комментарий' : props.parentId ? 'Ответить' : 'Добавить комментарий');
const submitLabel = computed(() => props.mode === 'edit' ? 'Сохранить изменения' : 'Опубликовать');
const previewAttachments = computed(() => savedAttachments.value);

function fieldError(field) { const value = fieldErrors.value?.[field]; return Array.isArray(value) ? value[0] : value; }
function toggleKeep(id) { keptIds.value = keptIds.value.includes(id) ? keptIds.value.filter((value) => value !== id) : [...keptIds.value, id]; }
function openDraftImage(file) { draftImage.value = { src: file.preview_url ?? file.url, alt: file.name }; }

async function togglePreview() {
    if (!hasText.value) { previewOpen.value = true; previewHtml.value = ''; return; }
    previewing.value = true;
    formError.value = '';
    try { previewHtml.value = await previewComment(text.value); previewOpen.value = true; }
    catch (error) { formError.value = apiErrorMessage(error); }
    finally { previewing.value = false; }
}

async function submit() {
    busy.value = true;
    formError.value = '';
    fieldErrors.value = {};
    const payload = { text: text.value };
    if (props.mode === 'create') {
        if (!auth.authenticated) Object.assign(payload, { guest_name: guest.value.name, guest_email: guest.value.email, guest_home_page: guest.value.homePage || undefined });
        if (props.parentId) payload.parent_id = props.parentId;
        payload['g-recaptcha-response'] = captchaToken.value;
        payload.attachments = files.value;
    } else {
        payload.attachments_to_keep = keptIds.value;
        payload.attachments = files.value;
    }

    try {
        const saved = props.mode === 'edit' ? await updateComment(props.comment.id, payload) : await createComment(payload);
        emit('saved', saved);
    } catch (error) {
        fieldErrors.value = apiFieldErrors(error);
        formError.value = error?.response?.status === 422 ? 'Проверьте отмеченные поля и CAPTCHA.' : apiErrorMessage(error);
        if (fieldErrors.value['g-recaptcha-response']) captcha.value?.reset();
    } finally { busy.value = false; }
}

watch(() => props.comment, (comment) => {
    text.value = comment?.body_html ?? '';
    keptIds.value = comment?.attachments?.map((file) => file.id) ?? [];
    files.value = [];
});
</script>
