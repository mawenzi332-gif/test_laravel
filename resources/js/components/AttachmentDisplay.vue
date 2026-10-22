<template>
    <div v-if="attachments.length || draftFiles.length" class="comment-attachments mt-3" aria-label="Вложения">
        <template v-for="attachment in attachments" :key="attachment.id">
            <button v-if="isImage(attachment) && (attachment.preview_url || attachment.url)" type="button" class="attachment-image-button" :aria-label="`Открыть изображение ${displayName(attachment)}`" @click="$emit('image', attachment)">
                <img :src="attachment.preview_url || attachment.url" :alt="displayName(attachment)" class="attachment-preview">
            </button>
            <a v-else-if="isImage(attachment)" class="attachment-file" :href="attachment.url" target="_blank" rel="noopener">{{ displayName(attachment) }} <span>Открыть изображение</span></a>
            <a v-else class="attachment-file" :href="attachment.url" target="_blank" rel="noopener">{{ displayName(attachment) }} <span>Скачать TXT</span></a>
            <a v-if="isImage(attachment) && attachment.preview_url" class="small align-self-end pb-1" :href="attachment.url" target="_blank" rel="noopener">Оригинал</a>
        </template>
        <template v-for="(file, index) in draftFiles" :key="file._draftKey ?? index">
            <button v-if="file.type.startsWith('image/') && file._previewUrl" type="button" class="attachment-image-button" :aria-label="`Открыть изображение ${file.name}`" @click="$emit('image', { name: file.name, preview_url: file._previewUrl })"><img :src="file._previewUrl" :alt="file.name" class="attachment-preview"></button>
            <span v-else class="attachment-file">{{ file.name }} <span>Будет прикреплён</span></span>
        </template>
    </div>
</template>

<script setup>
defineProps({ attachments: { type: Array, default: () => [] }, draftFiles: { type: Array, default: () => [] } });
defineEmits(['image']);
function isImage(file) { return file.mime_type?.startsWith('image/') || ['image/jpeg', 'image/png', 'image/gif'].includes(file.type); }
function displayName(file) { return file.file_name ?? file.name; }
</script>
