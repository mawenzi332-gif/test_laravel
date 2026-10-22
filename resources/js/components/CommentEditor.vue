<template>
    <div class="comment-editor">
        <div v-if="editor" class="btn-toolbar gap-1 mb-2" role="toolbar" aria-label="Форматирование комментария">
            <button type="button" class="btn btn-sm" :class="editor.isActive('italic') ? 'btn-primary' : 'btn-outline-secondary'" aria-label="Курсив" title="Курсив" @click="editor.chain().focus().toggleItalic().run()"><i>I</i></button>
            <button type="button" class="btn btn-sm fw-bold" :class="editor.isActive('bold') ? 'btn-primary' : 'btn-outline-secondary'" aria-label="Полужирный" title="Полужирный" @click="editor.chain().focus().toggleBold().run()">B</button>
            <button type="button" class="btn btn-sm font-monospace" :class="editor.isActive('code') ? 'btn-primary' : 'btn-outline-secondary'" aria-label="Код" title="Код" @click="editor.chain().focus().toggleCode().run()">&lt;/&gt;</button>
            <button type="button" class="btn btn-sm" :class="editor.isActive('link') ? 'btn-primary' : 'btn-outline-secondary'" aria-label="Ссылка" title="Ссылка" @click="setLink">🔗</button>
        </div>
        <EditorContent :editor="editor" class="form-control comment-editor-input" />
    </div>
</template>

<script setup>
import { onBeforeUnmount, watch } from 'vue';
import { Editor, EditorContent } from '@tiptap/vue-3';
import Bold from '@tiptap/extension-bold';
import Code from '@tiptap/extension-code';
import Document from '@tiptap/extension-document';
import History from '@tiptap/extension-history';
import Italic from '@tiptap/extension-italic';
import Link from '@tiptap/extension-link';
import Paragraph from '@tiptap/extension-paragraph';
import Text from '@tiptap/extension-text';

const props = defineProps({ id: { type: String, default: undefined }, modelValue: { type: String, default: '' }, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);

const editor = new Editor({
    extensions: [Document, Paragraph, Text, Bold, Code, History, Italic, Link.configure({ openOnClick: false, autolink: false, protocols: ['http', 'https'], isAllowedUri: (url) => /^https?:\/\//i.test(url), HTMLAttributes: { rel: 'noopener noreferrer' } })],
    content: props.modelValue,
    editable: !props.disabled,
    editorProps: { attributes: { id: props.id, class: 'comment-prosemirror', 'aria-label': 'Текст комментария' } },
    onUpdate: ({ editor: instance }) => emit('update:modelValue', instance.getHTML()),
});

function setLink() {
    const current = editor.getAttributes('link').href ?? '';
    const href = window.prompt('Введите URL ссылки (http или https)', current);
    if (href === null) return;
    if (href.trim() === '') editor.chain().focus().extendMarkRange('link').unsetLink().run();
    else if (/^https?:\/\//i.test(href.trim())) editor.chain().focus().extendMarkRange('link').setLink({ href: href.trim() }).run();
    else window.alert('Ссылки должны начинаться с http:// или https://');
}

watch(() => props.modelValue, (value) => {
    if (value !== editor.getHTML()) editor.commands.setContent(value || '', { emitUpdate: false });
});
watch(() => props.disabled, (value) => editor.setEditable(!value));
onBeforeUnmount(() => editor.destroy());
</script>
