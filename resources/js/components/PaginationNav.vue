<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import DButton from './DButton.vue';

const props = defineProps({ pagination: { type: Object, required: true } });
const route = useRoute();
const at = (page) => ({ query: { ...route.query, page: page > 1 ? page : undefined } });
// Shown under every list that has rows, even a single page, so every list
// ends the same way: the range on the left, the page controls on the right.
const show = computed(() => Boolean(props.pagination.range));

// 1 … 4 5 6 … 474: the first and last page, and the pages around the current one.
const numbers = computed(() => {
    const last = props.pagination.last_page;
    const current = props.pagination.page;
    if (!last) return [];

    const pages = [...new Set([1, current - 1, current, current + 1, last])]
        .filter((p) => p >= 1 && p <= last)
        .sort((a, b) => a - b);

    return pages.flatMap((p, i) => (i > 0 && p - pages[i - 1] > 1 ? ['…', p] : [p]));
});
</script>

<template>
    <nav v-if="show" class="dbg-pagination" aria-label="Pages">
        <span class="dbg-pagination__range">{{ pagination.range }}</span>
        <span class="dbg-pagination__buttons">
            <DButton v-if="pagination.has_previous" variant="secondary" :to="at(pagination.page - 1)">Previous</DButton>
            <DButton v-else variant="secondary" disabled>Previous</DButton>
            <span v-if="numbers.length" class="dbg-pagination__numbers">
                <template v-for="(n, i) in numbers" :key="i">
                    <span v-if="n === '…'" class="dbg-pagination__gap" aria-hidden="true">…</span>
                    <RouterLink v-else :to="at(n)" class="dbg-pagination__page" :aria-current="n === pagination.page ? 'page' : null"
                        :aria-label="`Page ${n}`">{{ n.toLocaleString() }}</RouterLink>
                </template>
            </span>
            <DButton v-if="pagination.has_next" variant="secondary" :to="at(pagination.page + 1)">Next</DButton>
            <DButton v-else variant="secondary" disabled>Next</DButton>
        </span>
    </nav>
</template>
