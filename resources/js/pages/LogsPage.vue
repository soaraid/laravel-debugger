<script setup>
import { ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api';
import { config } from '../config';
import { useResource } from '../useResource';
import ConfirmButton from '../components/ConfirmButton.vue';
import MenuButton from '../components/MenuButton.vue';
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
const { data, failed, reload } = useResource('/logs');

// Clear and Delete come from a file's ⋮ menu, then ask in a dialog.
const pendingClear = ref(null);
const confirmClear = ref(null);
const pendingDelete = ref(null);
const confirmDelete = ref(null);

function askClear(name) {
    pendingClear.value = name;
    confirmClear.value.open();
}

function askDelete(name) {
    pendingDelete.value = name;
    confirmDelete.value.open();
}

const clearFile = () => api.post('/logs/clear', { file: pendingClear.value }).then(reload);

async function deleteFile() {
    const name = pendingDelete.value;
    await api.delete('/logs', { file: name });
    // The open file is gone: show whichever file is newest now.
    if (name === data.value.file) await router.push({ query: {} });
    else reload();
}
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
    <div v-else class="dbg-split dbg-fill">
        <nav class="dbg-side" aria-label="Log files">
            <p class="dbg-side__label">Files</p>
            <div v-for="item in data.files" :key="item.name" class="dbg-side__row" :class="{ 'is-current': item.name === data.file }">
                <RouterLink :to="fileLink(item.name)" class="dbg-side__item" :aria-current="item.name === data.file ? 'page' : null">
                    <span>{{ item.name }}</span><span>{{ item.size }}</span>
                </RouterLink>
                <MenuButton :label="`Actions for ${item.name}`">
                    <button type="button" class="dbg-menu__item" role="menuitem" :disabled="config.readOnly"
                        :title="config.readOnly ? 'Read-only: actions are turned off in this environment.' : null" @click="askClear(item.name)">
                        Clear file
                    </button>
                    <button type="button" class="dbg-menu__item dbg-menu__item--danger" role="menuitem" :disabled="config.readOnly"
                        :title="config.readOnly ? 'Read-only: actions are turned off in this environment.' : null" @click="askDelete(item.name)">
                        Delete file
                    </button>
                </MenuButton>
            </div>
            <p v-if="!data.files.length" class="dbg-side__label">None yet</p>
        </nav>
        <ConfirmButton ref="confirmClear" :trigger="false" label="Clear file" :title="`Clear ${pendingClear}?`"
            body="Every entry in this file is removed. The file stays, and new entries are written to it as usual." confirm="Clear file"
            busy-label="Clearing…" :action="clearFile" />
        <ConfirmButton ref="confirmDelete" :trigger="false" label="Delete file" :title="`Delete ${pendingDelete}?`"
            body="The file is removed from storage/logs and can't be recovered." confirm="Delete file" busy-label="Deleting…" :action="deleteFile" />

        <div class="dbg-split__main dbg-fill__list">
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
