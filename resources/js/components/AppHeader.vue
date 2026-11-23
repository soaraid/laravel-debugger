<script setup>
import { computed } from 'vue';
import { activity } from '../api';
import { config } from '../config';

const tabs = computed(() => Object.entries(config.modules || {}));
</script>

<template>
    <header class="dbg-header">
        <div class="dbg-progress" :class="{ 'is-active': activity.pending > 0 }" aria-hidden="true"></div>
        <span class="dbg-header__brand">{{ config.app }}</span>
        <nav class="dbg-header__tabs" aria-label="Sections">
            <RouterLink v-for="[key, label] in tabs" :key="key" :to="`/${key}`" class="dbg-tab">{{ label }}</RouterLink>
        </nav>
        <a class="dbg-header__back" :href="config.home">Back to {{ config.app }}</a>
    </header>
</template>
