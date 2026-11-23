<script setup>
import { computed } from 'vue';
import { api } from '../api';
import { config } from '../config';
import { useResource } from '../useResource';
import ActionButton from '../components/ActionButton.vue';
import CodeBlock from '../components/CodeBlock.vue';
import ConfirmButton from '../components/ConfirmButton.vue';
import DataTable from '../components/DataTable.vue';
import EmptyState from '../components/EmptyState.vue';
import LoadingState from '../components/LoadingState.vue';
import Notice from '../components/Notice.vue';
import PageTitle from '../components/PageTitle.vue';
import PaginationNav from '../components/PaginationNav.vue';
import RowToggle from '../components/RowToggle.vue';
import TableRow from '../components/TableRow.vue';

const { data, failed, reload } = useResource('/queue');

const summary = computed(() => {
    if (!data.value) return null;
    const w = data.value.waiting;
    return `${data.value.total} failed · ` + (w.waiting !== null ? `${w.waiting.toLocaleString()} waiting on ${w.connection}/${w.queue}` : `connection ${w.connection}`);
});

const columns = [
    { label: 'Job', width: 'minmax(0, 1fr)' },
    { label: 'Queue', width: '96px' },
    { label: 'Error', width: 'minmax(0, 2fr)' },
    { label: 'Failed', width: '120px' },
    { label: '', width: '140px' },
];

const retry = (job) => api.post(`/queue/${job.id}/retry`).then(reload);
const remove = (job) => api.delete(`/queue/${job.id}`).then(reload);
const retryAll = () => api.post('/queue/retry-all').then(reload);
</script>

<template>
    <LoadingState v-if="!data" :failed="failed" />
    <template v-else>
        <PageTitle title="Failed jobs" :summary="summary">
            <template v-if="data.total > 0" #actions>
                <ConfirmButton label="Retry all" variant="secondary" :title="`Retry all ${data.total} failed jobs?`"
                    body="Every failed job goes back to its queue and runs again. Jobs that send emails or charge payments will do so again."
                    confirm="Retry all jobs" busy-label="Retrying…" confirm-variant="primary" :disabled="config.readOnly" :action="retryAll" />
            </template>
        </PageTitle>

        <Notice v-if="data.error" tone="danger">
            Couldn't read failed jobs: {{ data.error }}. Check queue.failed in config/queue.php and that the failed jobs table exists.
        </Notice>
        <EmptyState v-else-if="!data.jobs.length" title="No failed jobs" description="Jobs that throw an exception land here so you can retry them." />
        <div v-else class="dbg-fill dbg-fill__list">
            <DataTable :columns="columns">
                <TableRow v-for="job in data.jobs" :key="job.id" expandable>
                    <span class="dbg-cell dbg-cell--mono dbg-cell--strong dbg-cell--truncate"><RowToggle>{{ job.name }}</RowToggle></span>
                    <span class="dbg-cell dbg-cell--muted dbg-cell--truncate">{{ job.queue }}</span>
                    <span class="dbg-cell dbg-cell--mono dbg-cell--truncate" style="color: var(--color-text-secondary)">{{ job.error }}</span>
                    <span class="dbg-cell dbg-cell--muted dbg-cell--num">{{ job.failed_ago }}</span>
                    <span class="dbg-cell dbg-cell--actions">
                        <ActionButton :disabled="config.readOnly" busy-label="Retrying…" :action="() => retry(job)">Retry</ActionButton>
                        <ConfirmButton label="Delete" :title="`Delete failed job ${job.name}?`" body="It's removed from failed jobs and can't be retried afterwards."
                            confirm="Delete job" busy-label="Deleting…" :disabled="config.readOnly" :action="() => remove(job)" />
                    </span>
                    <template #detail>
                        <p style="color: var(--color-text-secondary)">
                            {{ job.class }}<template v-if="job.attempts !== null"> · attempt {{ job.attempts }}</template>
                            · failed {{ job.failed_at }} · {{ job.connection }} · id <span style="font-family: var(--font-mono)">{{ job.id }}</span>
                        </p>
                        <CodeBlock>{{ job.exception }}</CodeBlock>
                    </template>
                </TableRow>
            </DataTable>
            <PaginationNav :pagination="data.pagination" />
            <p class="dbg-note">Retry sends the job back to its queue. Delete removes it from failed jobs and can't be undone.</p>
        </div>
    </template>
</template>
