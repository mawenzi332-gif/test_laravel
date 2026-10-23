<template>
    <article class="comment-node" :class="{ 'comment-depth-capped': level >= 3 }" :style="{ '--comment-depth': Math.min(level, 3), '--child-indent': level < 3 ? '0.5rem' : '0rem' }">
        <div v-if="comment.is_deleted" class="comment-tombstone" role="note">Комментарий удалён пользователем</div>
        <template v-else>
            <header class="comment-header">
                <div class="min-w-0">
                    <a v-if="comment.author?.home_page" :href="comment.author.home_page" target="_blank" rel="noopener noreferrer" class="comment-author">{{ comment.author.name }}</a>
                    <strong v-else class="comment-author">{{ comment.author?.name }}</strong>
                    <span class="comment-meta">{{ comment.author?.email }} · {{ formatDate(comment.created_at) }}<span v-if="comment.edited_at" class="edited-label"> · отредактирован</span></span>
                </div>
                <span class="hierarchy-label">{{ levelLabel }}</span>
            </header>
            <div class="comment-body" v-html="comment.body_html"></div>
            <AttachmentDisplay :attachments="comment.attachments ?? []" @image="showImage" />
            <div class="comment-actions mt-3">
                <button type="button" class="btn btn-sm btn-outline-primary" @click="replying = !replying">{{ replying ? 'Отмена' : 'Ответить' }}</button>
                <button v-if="comment.can_edit" type="button" class="btn btn-sm btn-outline-secondary" @click="editing = !editing">{{ editing ? 'Отмена' : 'Редактировать' }}</button>
                <button v-if="comment.can_delete" type="button" class="btn btn-sm btn-outline-danger" :disabled="deleting" @click="remove">{{ deleting ? 'Удаляем…' : 'Удалить' }}</button>
            </div>
            <CommentComposer v-if="editing" :key="`edit-${comment.id}`" mode="edit" :comment="comment" class="mt-3" @saved="onSaved" @cancel="editing = false" />
        </template>

        <CommentComposer v-if="replying" :key="`reply-${comment.id}`" class="mt-3" :parent-id="comment.id" :parent-author="comment.author?.name ?? ''" dismissible @saved="onSaved" @cancel="replying = false" />
        <div v-if="comment.children?.length" class="comment-children">
            <CommentNode v-for="child in comment.children" :key="child.id" :comment="child" :level="level + 1" @changed="$emit('changed')" @image="showImage" />
        </div>
        <ImageViewer v-if="image" :src="image.src" :alt="image.alt" @close="image = null" />
    </article>
</template>

<script setup>
import { defineAsyncComponent, ref } from 'vue';
import { deleteComment } from '../api/comments.js';
import { apiErrorMessage } from '../api/client.js';
import { notify } from '../feedback.js';
import AttachmentDisplay from './AttachmentDisplay.vue';
import ImageViewer from './ImageViewer.vue';

defineOptions({ name: 'CommentNode' });
const CommentComposer = defineAsyncComponent(() => import('./CommentComposer.vue'));
const props = defineProps({ comment: { type: Object, required: true }, level: { type: Number, default: 0 } });
const emit = defineEmits(['changed', 'image']);
const replying = ref(false);
const editing = ref(false);
const deleting = ref(false);
const image = ref(null);
const levelLabel = props.level === 0 ? 'Корневой комментарий' : `Ответ · уровень ${props.level}`;

function formatDate(value) { return new Intl.DateTimeFormat('ru-RU', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)); }
function showImage(file) { image.value = { src: file.url ?? file.preview_url, alt: file.name }; }
function onSaved() { replying.value = false; editing.value = false; emit('changed'); }

async function remove() {
    if (!window.confirm('Удалить этот комментарий? Его ответы сохранятся.')) return;
    deleting.value = true;
    try { await deleteComment(props.comment.id); emit('changed'); }
    catch (error) { notify(apiErrorMessage(error)); }
    finally { deleting.value = false; }
}
</script>
