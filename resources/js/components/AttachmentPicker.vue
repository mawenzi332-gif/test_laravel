<template>
    <div>
        <label class="form-label" :for="inputId">Вложения <span class="text-body-secondary">(до 3 файлов, JPG/PNG/GIF или TXT)</span></label>
        <input :id="inputId" class="form-control" type="file" accept=".jpg,.jpeg,.png,.gif,.txt,image/jpeg,image/png,image/gif,text/plain" multiple :disabled="disabled || totalCount >= maxAttachments" @change="onChoose">
        <div class="form-text">Изображения: до {{ imageLimit }} МБ каждое. TXT: до 100 КБ.</div>
        <div v-if="items.length" class="attachment-drafts mt-3">
            <div v-for="item in items" :key="item.key" class="draft-attachment">
                <img v-if="item.previewUrl && item.isImage" :src="item.previewUrl" :alt="item.name" class="draft-thumb">
                <span v-else class="draft-file-icon" aria-hidden="true">TXT</span>
                <span class="text-truncate flex-grow-1">{{ item.name }} <small class="text-body-secondary">{{ formatSize(item.size) }}</small></span>
                <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="disabled" @click="replace(item.key)">Заменить</button>
                <button type="button" class="btn btn-sm btn-outline-danger" :disabled="disabled" :aria-label="`Удалить ${item.name}`" @click="remove(item.key)">Удалить</button>
                <input :ref="(element) => setReplaceInput(item.key, element)" class="visually-hidden" type="file" accept=".jpg,.jpeg,.png,.gif,.txt,image/jpeg,image/png,image/gif,text/plain" @change="(event) => onReplace(event, item.key)">
            </div>
        </div>
        <ul v-if="errors.length" class="list-unstyled mt-2 mb-0 small text-danger" role="alert"><li v-for="message in errors" :key="message">{{ message }}</li></ul>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({ modelValue: { type: Array, default: () => [] }, disabled: Boolean, imageLimit: { type: Number, default: Number(import.meta.env.VITE_MEDIA_IMAGE_MAX_MB ?? 10) }, maxAttachments: { type: Number, default: 3 }, inputId: { type: String, default: 'comment-attachments' } });
const emit = defineEmits(['update:modelValue']);
const errors = ref([]);
const replaceInputs = new Map();
const items = computed(() => props.modelValue.map((file) => ({ key: file._draftKey, file, name: file.name, size: file.size, isImage: file.type.startsWith('image/'), previewUrl: file._previewUrl })));
const totalCount = computed(() => items.value.length);

function setReplaceInput(key, element) { if (element) replaceInputs.set(key, element); else replaceInputs.delete(key); }
function replace(key) { replaceInputs.get(key)?.click(); }
function onChoose(event) { addFiles([...event.target.files]); event.target.value = ''; }
function onReplace(event, key) { const file = event.target.files?.[0]; if (file) replaceFile(key, file); event.target.value = ''; }

function normalize(file) {
    const normalized = new File([file], file.name, { type: file.type, lastModified: file.lastModified });
    Object.defineProperties(normalized, {
        _draftKey: { value: `${Date.now()}-${Math.random()}`, configurable: true },
        _previewUrl: { value: file.type.startsWith('image/') ? URL.createObjectURL(file) : null, configurable: true },
    });
    return normalized;
}

function validate(file, remainingSlots) {
    const name = file.name.toLowerCase();
    const image = ['.jpg', '.jpeg', '.png', '.gif'].some((extension) => name.endsWith(extension));
    const text = name.endsWith('.txt');
    if (!image && !text) return 'Можно прикрепить только JPG, PNG, GIF или TXT.';
    if (image && file.size > props.imageLimit * 1024 * 1024) return `${file.name}: размер изображения превышает ${props.imageLimit} МБ.`;
    if (text && file.size > 100 * 1024) return `${file.name}: TXT должен быть не больше 100 КБ.`;
    if (!remainingSlots) return 'К одному комментарию можно прикрепить не более 3 файлов.';
    return null;
}

function addFiles(files) {
    errors.value = [];
    const accepted = [...props.modelValue];
    files.forEach((file) => {
        const issue = validate(file, props.maxAttachments - accepted.length);
        if (issue) errors.value.push(issue);
        else accepted.push(normalize(file));
    });
    emit('update:modelValue', accepted);
}

function replaceFile(key, file) {
    const issue = validate(file, 1);
    if (issue) { errors.value = [issue]; return; }
    errors.value = [];
    const next = props.modelValue.map((current) => current._draftKey === key ? normalize(file) : current);
    emit('update:modelValue', next);
}

function remove(key) { errors.value = []; emit('update:modelValue', props.modelValue.filter((file) => file._draftKey !== key)); }
function formatSize(bytes) { return bytes < 1024 ? `${bytes} Б` : `${Math.round(bytes / 1024)} КБ`; }
watch(() => props.modelValue, (next, previous = []) => {
    const live = new Set(next.map((file) => file._previewUrl).filter(Boolean));
    previous.forEach((file) => { if (file._previewUrl && !live.has(file._previewUrl)) URL.revokeObjectURL(file._previewUrl); });
});
onBeforeUnmount(() => props.modelValue.forEach((file) => { if (file._previewUrl) URL.revokeObjectURL(file._previewUrl); }));
</script>
