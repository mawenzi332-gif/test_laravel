<template>
    <div class="comment-editor">
        <div v-if="editor" class="btn-toolbar gap-1 mb-2" role="toolbar" aria-label="Форматирование комментария">
            <button type="button" class="btn btn-sm" :class="editor.isActive('italic') ? 'btn-primary' : 'btn-outline-secondary'" aria-label="Курсив" title="Курсив" :aria-pressed="editor.isActive('italic')" @click="toggleItalic"><i>I</i></button>
            <button type="button" class="btn btn-sm fw-bold" :class="editor.isActive('bold') ? 'btn-primary' : 'btn-outline-secondary'" aria-label="Полужирный" title="Полужирный" :aria-pressed="editor.isActive('bold')" @click="toggleBold">B</button>
            <button type="button" class="btn btn-sm font-monospace" :class="editor.isActive('code') ? 'btn-primary' : 'btn-outline-secondary'" aria-label="Код" title="Код" :aria-pressed="editor.isActive('code')" @click="toggleCode">&lt;/&gt;</button>
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
import { notify } from '../feedback.js';

const props = defineProps({ id: { type: String, default: undefined }, modelValue: { type: String, default: '' }, disabled: Boolean });
const emit = defineEmits(['update:modelValue']);

const editor = new Editor({
    extensions: [Document, Paragraph, Text, Bold, Code, History, Italic, Link.configure({ openOnClick: false, autolink: false, protocols: ['http', 'https'], isAllowedUri: (url) => /^https?:\/\//i.test(url), HTMLAttributes: { rel: 'noopener noreferrer' } })],
    content: props.modelValue,
    editable: !props.disabled,
    editorProps: { attributes: { id: props.id, class: 'comment-prosemirror', 'aria-label': 'Текст комментария' } },
    onUpdate: ({ editor: instance }) => emit('update:modelValue', instance.getHTML()),
});

function toggleItalic() {
    if (editor.isActive('code')) {
        notify('Код нельзя сочетать с курсивом. Сначала снимите форматирование кода.', 'warning');
        return;
    }
    editor.chain().focus().toggleItalic().run();
}

function toggleBold() {
    if (editor.isActive('code')) {
        notify('Код нельзя сочетать с полужирным форматированием. Сначала снимите форматирование кода.', 'warning');
        return;
    }
    editor.chain().focus().toggleBold().run();
}

function toggleCode() {
    if (editor.isActive('code')) {
        editor.chain().focus().toggleCode().run();
        return;
    }
    const existing = [editor.isActive('bold') && 'полужирный', editor.isActive('italic') && 'курсив'].filter(Boolean);
    if (existing.length) {
        notify(`Код нельзя сочетать с форматированием: ${existing.join(' и ')}. Сначала снимите его.`, 'warning');
        return;
    }
    editor.chain().focus().toggleCode().run();
}

function setLink() {
    if (editor.isActive('link')) {
        editor.chain().focus().extendMarkRange('link').unsetLink().run();
        notify('Ссылка снята с выделенного текста.', 'success');
        return;
    }
    if (editor.isActive('code')) {
        notify('Ссылку нельзя применить к фрагменту кода. Снимите форматирование кода или выделите другой текст.', 'warning');
        return;
    }
    const current = editor.getAttributes('link').href ?? '';
    const href = window.prompt('Введите URL ссылки (http или https)', current);
    if (href === null) return;
    if (/^https?:\/\//i.test(href.trim())) editor.chain().focus().setLink({ href: href.trim() }).run();
    else notify('Ссылка не применена. Адрес должен начинаться с http:// или https://.', 'warning');
}

watch(() => props.modelValue, (value) => {
    if (value !== editor.getHTML()) editor.commands.setContent(value || '', { emitUpdate: false });
});
watch(() => props.disabled, (value) => editor.setEditable(!value));
onBeforeUnmount(() => editor.destroy());
</script>
