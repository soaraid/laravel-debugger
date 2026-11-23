<script setup>
defineProps({ title: { type: String, required: true }, summary: { type: String, default: null } });
</script>

<template>
    <div class="dbg-page-title">
        <div class="dbg-page-title__text">
            <h1>{{ title }}</h1>
            <p v-if="summary">{{ summary }}</p>
        </div>
        <div v-if="$slots.actions" class="dbg-page-title__actions"><slot name="actions" /></div>
    </div>
</template>
