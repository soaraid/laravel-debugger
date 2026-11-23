<script setup>
defineProps({ title: { type: String, required: true }, description: { type: String, default: null } });
</script>

<template>
    <div class="dbg-box">
        <div class="dbg-empty">
            <p class="dbg-empty__title">{{ title }}</p>
            <p v-if="description" class="dbg-empty__description">{{ description }}</p>
            <div v-if="$slots.action" class="dbg-empty__action"><slot name="action" /></div>
        </div>
    </div>
</template>
