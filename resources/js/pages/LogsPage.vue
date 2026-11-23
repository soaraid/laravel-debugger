<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useResource } from '../useResource';
import DataTable from '../components/DataTable.vue';
import DButton from '../components/DButton.vue';
import EmptyState from '../components/EmptyState.vue';
import LevelText from '../components/LevelText.vue';
import LogEntryDetail from '../components/LogEntryDetail.vue';
import LoadingState from '../components/LoadingState.vue';
import PaginationNav from '../components/PaginationNav.vue';
import RowToggle from '../components/RowToggle.vue';
import TableRow from '../components/TableRow.vue';

const route = useRoute();
const router = useRouter();
const { data, failed } = useResource('/logs');
const search = ref(route.query.q || '');

watch(() => route.query.q, (q) => (search.value = q || ''));

const columns = [
    { label: 'Time', width: '96px' },
    { label: 'Level', width: '72px' },
    { label: 'Message', width: 'minmax(0, 1fr)' },
];

function filter(changes) {
    router.push({ query: { ...route.query, ...changes, page: undefined } });
}

const fileLink = (name) => ({ query: { file: name } });
</script>

<template>
    <h1 class="dbg-sr-only">Logs</h1>
    <LoadingState v-if="!data" :failed="failed" />
    <div v-else class="dbg-split">
        <nav class="dbg-side" aria-label="Log files">
            <p class="dbg-side__label">Files</p>
            <RouterLink v-for="item in data.files" :key="item.name" :to="fileLink(item.name)" class="dbg-side__item"
                :aria-current="item.name === data.file ? 'page' : null">
                <span>{{ item.name }}</span><span>{{ item.size }}</span>
            </RouterLink>
            <p v-if="!data.files.length" class="dbg-side__label">None yet</p>
        </nav>

        <div class="dbg-split__main">
            <EmptyState v-if="!data.file" title="No log files yet" description="Laravel writes to storage/logs once something is logged." />
            <template v-else>
                <form class="dbg-toolbar" role="search" @submit.prevent="filter({ q: search || undefined })">
                    <label class="dbg-sr-only" for="log-search">Search</label>
                    <input id="log-search" v-model="search" class="dbg-input" type="search" placeholder="Search messages, e.g. smtp">
                    <label class="dbg-sr-only" for="log-level">Level</label>
                    <select id="log-level" class="dbg-select" :value="data.level || ''" @change="filter({ level: $event.target.value || undefined })">
                        <option value="">All levels</option>
                        <option v-for="option in data.levels.filter((l) => data.level_counts[l] || l === data.level)" :key="option" :value="option">
                            {{ option[0].toUpperCase() + option.slice(1) }} ({{ (data.level_counts[option] || 0).toLocaleString() }})
                        </option>
                    </select>
                    <span class="dbg-toolbar__meta">{{ data.total.toLocaleString() }} {{ data.total === 1 ? 'entry' : 'entries' }}</span>
                </form>

                <EmptyState v-if="!data.entries.length && (data.search || data.level)"
                    :title="data.search ? `No entries match “${data.search}”` : `No ${data.level} entries`"
                    description="Try another word or a different level.">
                    <template #action>
                        <DButton variant="secondary" :to="{ query: { file: data.file } }">Clear filters</DButton>
                    </template>
                </EmptyState>
                <EmptyState v-else-if="!data.entries.length" title="This file is empty" description="New entries show up here as they're written." />

                <template v-else>
                    <DataTable :columns="columns">
                        <TableRow v-for="(entry, i) in data.entries" :key="`${data.pagination.page}-${i}-${entry.datetime}`" expandable>
                            <span class="dbg-cell dbg-cell--muted dbg-cell--num">
                                <RowToggle><time :datetime="entry.datetime">{{ entry.time }}</time></RowToggle>
                            </span>
                            <span class="dbg-cell"><LevelText :level="entry.level" :tone="entry.tone" /></span>
                            <span class="dbg-cell dbg-cell--mono dbg-cell--truncate" :title="entry.summary.slice(0, 300)">{{ entry.summary }}</span>

                            <template #detail>
                                <LogEntryDetail :entry="entry" />
                            </template>
                        </TableRow>
                    </DataTable>
                    <PaginationNav :pagination="data.pagination" />
                </template>
            </template>
        </div>
    </div>
</template>
