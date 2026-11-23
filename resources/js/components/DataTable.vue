<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { navigation } from '../useResource';
import SpinnerMark from './SpinnerMark.vue';

// columns: [{ label: 'Time', width: '96px', end: false }]. Rows inherit the
// same grid through the --dbg-cols custom property. While the page's data
// is loading, the rows dim under a spinner (after a short delay, so fast
// responses never flash).
const props = defineProps({ columns: { type: Array, required: true } });
const template = computed(() => props.columns.map((c) => c.width || 'minmax(0, 1fr)').join(' '));
const body = ref(null);
const route = useRoute();

// When the rows scroll inside the table (full-screen pages), another page
// or filter starts at the first row again.
watch(() => route.query, () => body.value?.scrollTo?.({ top: 0 }));
</script>

<template>
    <div class="dbg-table" role="table" :style="{ '--dbg-cols': template }" :aria-busy="navigation.loading ? 'true' : 'false'">
        <div class="dbg-table__head" role="row">
            <span v-for="(column, i) in columns" :key="i" role="columnheader" :class="{ 'is-end': column.end }">{{ column.label }}</span>
        </div>
        <div ref="body" class="dbg-table__body" role="rowgroup">
            <slot />
        </div>
        <div class="dbg-table__loading" aria-hidden="true"><SpinnerMark /></div>
    </div>
</template>
