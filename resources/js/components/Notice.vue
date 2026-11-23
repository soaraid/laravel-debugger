<script setup>
// tone: success | danger | info
defineProps({ tone: { type: String, default: 'success' } });
</script>

<template>
    <p :class="['dbg-notice', `dbg-notice--${tone}`]" :role="tone === 'danger' ? 'alert' : 'status'"><slot /></p>
</template>
