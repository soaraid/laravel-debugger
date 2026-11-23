<script setup>
defineProps({ title: { type: String, required: true }, description: { type: String, default: null } });
</script>

<template>
    <div class="dbg-action-row">
        <div class="dbg-action-row__text">
            <span class="dbg-action-row__title">{{ title }}</span>
            <span v-if="description" class="dbg-action-row__description">{{ description }}</span>
        </div>
        <slot />
    </div>
</template>
