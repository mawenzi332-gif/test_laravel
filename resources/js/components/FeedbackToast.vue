<template>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" aria-live="assertive" aria-atomic="true">
        <div v-if="message" class="toast show" :class="`feedback-toast-${variant}`" :role="variant === 'danger' ? 'alert' : 'status'">
            <div class="d-flex align-items-start">
                <div class="toast-body">{{ message }}</div>
                <button type="button" class="btn-close me-2 mt-2" aria-label="Закрыть уведомление" @click="dismiss"></button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const message = ref('');
const variant = ref('danger');
let timeout;

function show(event) {
    message.value = event.detail?.message ?? '';
    variant.value = event.detail?.variant ?? 'danger';
    window.clearTimeout(timeout);
    timeout = window.setTimeout(dismiss, 6000);
}

function dismiss() {
    message.value = '';
    window.clearTimeout(timeout);
}

onMounted(() => window.addEventListener('app:feedback', show));
onBeforeUnmount(() => {
    window.removeEventListener('app:feedback', show);
    window.clearTimeout(timeout);
});
</script>
