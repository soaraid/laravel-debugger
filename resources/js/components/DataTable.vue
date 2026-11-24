<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, onUpdated, ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import { navigation } from '../useResource';
import SpinnerMark from './SpinnerMark.vue';

// columns: [{ label: 'Time', width: '96px', end: false }]. Rows inherit the
// same grid through the --dbg-cols custom property. While the page's data
// is loading, the rows dim under a spinner (after a short delay, so fast
// responses never flash).
const props = defineProps({ columns: { type: Array, required: true } });
const template = computed(() => props.columns.map((c) => c.width || 'minmax(0, 1fr)').join(' '));
const root = ref(null);
const body = ref(null);
const route = useRoute();

// A page's main list (inside .dbg-fill__list) gets a maximum height: what is
// left of the window below it, so the pagination under it stays in view, but
// never less than MIN_PX. The page itself is never locked to the window: on a
// short screen (a tablet in landscape) the list keeps a usable height and the
// page scrolls instead.
const MIN_PX = 320;
const wide = window.matchMedia('(min-width: 961px)');
const maxHeight = ref(null);

function fit() {
    if (!root.value?.closest('.dbg-fill__list') || !wide.matches) {
        maxHeight.value = null;
        return;
    }

    const box = body.value.getBoundingClientRect();
    const top = box.top + window.scrollY;
    // Everything under the list on the page: pagination, notes, footer.
    const below = document.documentElement.scrollHeight - (top + box.height);
    const room = Math.floor(window.innerHeight - top - below);
    const next = Math.max(MIN_PX, room);

    if (next !== maxHeight.value) maxHeight.value = next;
}

const refit = () => requestAnimationFrame(fit);

onMounted(() => {
    refit();
    window.addEventListener('resize', refit);
    wide.addEventListener('change', refit);
});
onUpdated(refit);
onBeforeUnmount(() => {
    window.removeEventListener('resize', refit);
    wide.removeEventListener('change', refit);
});

// Another page or filter starts at the first row again.
watch(() => route.query, async () => {
    body.value?.scrollTo?.({ top: 0 });
    await nextTick();
    refit();
});
</script>

<template>
    <div ref="root" class="dbg-table" role="table" :style="{ '--dbg-cols': template }" :aria-busy="navigation.loading ? 'true' : 'false'">
        <div class="dbg-table__head" role="row">
            <span v-for="(column, i) in columns" :key="i" role="columnheader" :class="{ 'is-end': column.end }">{{ column.label }}</span>
        </div>
        <div ref="body" class="dbg-table__body" role="rowgroup" :style="maxHeight ? { maxHeight: `${maxHeight}px` } : null">
            <slot />
        </div>
        <div class="dbg-table__loading" aria-hidden="true"><SpinnerMark /></div>
    </div>
</template>
