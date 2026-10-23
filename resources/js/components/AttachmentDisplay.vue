<template>
    <div v-if="attachments.length || draftFiles.length" class="comment-attachments mt-3" aria-label="Вложения">
        <template v-for="(attachment, index) in attachments" :key="attachment.id">
            <div v-if="isImage(attachment)" class="attachment-image-item">
                <button v-if="attachment.preview_url || attachment.url" type="button" class="attachment-image-button" :aria-label="`Открыть изображение ${displayNames[index]}`" @click="$emit('image', attachment)">
                    <img :src="attachment.preview_url || attachment.url" :alt="displayNames[index]" class="attachment-preview">
                </button>
                <span v-else class="attachment-name">{{ displayNames[index] }}</span>
                <a v-if="attachment.url" class="attachment-download" :href="attachment.url" :download="attachment.file_name ?? attachment.name">{{ imageDownloadLabel(attachment) }}</a>
            </div>
            <div v-else class="attachment-file"><span class="attachment-name">{{ displayNames[index] }}</span><a :href="attachment.url" target="_blank" rel="noopener">Скачать TXT</a></div>
        </template>
        <template v-for="(file, index) in draftFiles" :key="file._draftKey ?? index">
            <button v-if="file.type.startsWith('image/') && file._previewUrl" type="button" class="attachment-image-button" :aria-label="`Открыть изображение ${file.name}`" @click="$emit('image', { name: file.name, preview_url: file._previewUrl })"><img :src="file._previewUrl" :alt="file.name" class="attachment-preview"></button>
            <span v-else class="attachment-file"><span class="attachment-name">{{ displayNames[attachments.length + index] }}</span><span>Будет прикреплён</span></span>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { duplicateDisplayNames } from '../utils/attachmentNames.js';

const props = defineProps({ attachments: { type: Array, default: () => [] }, draftFiles: { type: Array, default: () => [] } });
defineEmits(['image']);
function isImage(file) { return file.mime_type?.startsWith('image/') || ['image/jpeg', 'image/png', 'image/gif'].includes(file.type); }
function displayName(file) { return file.file_name ?? file.name; }
function imageDownloadLabel(file) {
    const mimeType = (file.mime_type ?? file.type ?? '').toLowerCase();
    const name = (file.file_name ?? file.name ?? '').toLowerCase();
    return mimeType === 'image/gif' || name.endsWith('.gif') ? 'Скачать GIF' : 'Скачать изображение';
}
const displayNames = computed(() => duplicateDisplayNames([...props.attachments, ...props.draftFiles], displayName));
</script>
