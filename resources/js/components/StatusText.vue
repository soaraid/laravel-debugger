<script setup>
// tone: ok | warning | danger | muted
defineProps({ tone: { type: String, default: 'ok' } });
</script>

<template>
    <span :class="['dbg-status', `dbg-status--${tone}`]"><slot /></span>
</template>
