<template><div v-if="message" class="form-text text-danger" role="alert">{{ message }}</div></template>
<script setup>
defineProps({ message: { type: String, default: '' } });
</script>
