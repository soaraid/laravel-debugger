<script setup>
import { inject } from 'vue';

const row = inject('row');
</script>

<template>
    <button type="button" class="dbg-row__toggle" :aria-expanded="row.open.value ? 'true' : 'false'" @click="row.toggle"><slot /></button>
</template>
