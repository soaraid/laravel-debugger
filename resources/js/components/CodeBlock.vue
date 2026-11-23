<script setup>
defineProps({ wrap: { type: Boolean, default: false } });
</script>

<template>
    <pre :class="['dbg-code', { 'dbg-code--wrap': wrap }]"><slot /></pre>
</template>
