<template>
    <div class="captcha-widget">
        <div v-if="!siteKey" class="form-text text-warning">CAPTCHA не настроена: задайте NOCAPTCHA_SITEKEY.</div>
        <div v-else ref="container" class="captcha-container"></div>
        <div v-if="error" class="form-text text-danger" role="alert">{{ error }}</div>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

const emit = defineEmits(['update:modelValue']);
const siteKey = import.meta.env.VITE_NOCAPTCHA_SITEKEY;
const container = ref(null);
const error = ref('');
let widgetId;
let disposed = false;

function renderWidget() {
    if (disposed || !window.grecaptcha?.render || !container.value || widgetId !== undefined) return;
    widgetId = window.grecaptcha.render(container.value, {
        sitekey: siteKey,
        callback: (token) => { error.value = ''; emit('update:modelValue', token); },
        'expired-callback': () => emit('update:modelValue', ''),
        'error-callback': () => { error.value = 'Не удалось загрузить CAPTCHA. Проверьте соединение и попробуйте ещё раз.'; emit('update:modelValue', ''); },
    });
}

onMounted(() => {
    if (!siteKey) return;
    if (window.grecaptcha?.render) {
        window.grecaptcha.ready(renderWidget);
        return;
    }
    const existing = document.querySelector('script[data-recaptcha-api]');
    if (existing) {
        existing.addEventListener('load', () => window.grecaptcha?.ready(renderWidget), { once: true });
        return;
    }
    const script = document.createElement('script');
    script.src = 'https://www.google.com/recaptcha/api.js?render=explicit';
    script.async = true;
    script.defer = true;
    script.dataset.recaptchaApi = 'true';
    script.onload = () => window.grecaptcha?.ready(renderWidget);
    script.onerror = () => { error.value = 'Не удалось загрузить CAPTCHA. Проверьте соединение и попробуйте ещё раз.'; };
    document.head.append(script);
});

function reset() {
    if (widgetId !== undefined) window.grecaptcha?.reset(widgetId);
    emit('update:modelValue', '');
}

defineExpose({ reset });
onBeforeUnmount(() => { disposed = true; });
</script>
