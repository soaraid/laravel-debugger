<script setup>
defineProps({ level: { type: String, required: true }, tone: { type: String, required: true } });
</script>

<template>
    <span :class="['dbg-level', `dbg-level--${tone}`]">{{ level }}</span>
</template>
