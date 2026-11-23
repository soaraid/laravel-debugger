<script setup>
import SpinnerMark from './SpinnerMark.vue';

defineProps({ failed: { type: Boolean, default: false } });
</script>

<template>
    <p v-if="failed" class="dbg-note">This page couldn't load. Check the notice above, then reload.</p>
    <div v-else class="dbg-loading-page"><SpinnerMark /></div>
</template>
