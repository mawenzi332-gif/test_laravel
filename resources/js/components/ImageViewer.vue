<template>
    <div ref="modalElement" class="modal fade" tabindex="-1" aria-label="Просмотр изображения" aria-modal="true" role="dialog">
        <div class="modal-dialog modal-dialog-centered modal-xl"><div class="modal-content bg-dark"><div class="modal-header border-0 py-2"><span class="text-white small">{{ alt }}</span><button type="button" class="btn-close btn-close-white" aria-label="Закрыть" @click="close"></button></div><div class="modal-body text-center p-2"><img :src="src" :alt="alt" class="image-viewer-image"></div></div></div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Modal } from 'bootstrap';

const props = defineProps({ src: { type: String, required: true }, alt: { type: String, default: 'Изображение' } });
const emit = defineEmits(['close']);
const modalElement = ref(null);
let modal;
function close() { modal?.hide(); }
onMounted(() => {
    modal = new Modal(modalElement.value, { backdrop: true, keyboard: true });
    modalElement.value.addEventListener('hidden.bs.modal', () => emit('close'), { once: true });
    modal.show();
});
onBeforeUnmount(() => modal?.dispose());
</script>
