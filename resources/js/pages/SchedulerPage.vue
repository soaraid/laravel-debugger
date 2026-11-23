<script setup>
import { computed } from 'vue';
import { useResource } from '../useResource';
import DataTable from '../components/DataTable.vue';
import EmptyState from '../components/EmptyState.vue';
import LoadingState from '../components/LoadingState.vue';
import Notice from '../components/Notice.vue';
import PageTitle from '../components/PageTitle.vue';
import PaginationNav from '../components/PaginationNav.vue';
import StatusText from '../components/StatusText.vue';
import TableRow from '../components/TableRow.vue';

const { data, failed } = useResource('/scheduler');
const tones = { ran: 'ok', failed: 'danger', skipped: 'muted', running: 'muted' };

const summary = computed(() => data.value && (data.value.last_beat
    ? `Last run ${data.value.last_beat}. Runs are kept for ${data.value.retention} days.`
    : `Runs are kept for ${data.value.retention} days.`));

const taskColumns = [
    { label: 'Task', width: 'minmax(0, 1.4fr)' },
    { label: 'Schedule', width: 'minmax(0, 1fr)' },
    { label: 'Next run', width: '120px' },
    { label: 'Last run', width: '180px' },
];
const runColumns = [
    { label: 'Time', width: '110px' },
    { label: 'Task', width: 'minmax(0, 1fr)' },
    { label: 'Result', width: 'minmax(0, 1.4fr)' },
    { label: 'Took', width: '72px', end: true },
];
</script>

<template>
    <LoadingState v-if="!data" :failed="failed" />
    <template v-else>
        <PageTitle title="Scheduler" :summary="summary" />

        <Notice v-if="data.late_minutes !== null" tone="danger" class="dbg-page-notice">
            The scheduler hasn't run for {{ data.late_minutes }} minutes. Check that the cron entry for schedule:run is still installed on the server.
        </Notice>
        <Notice v-else-if="data.never_ran" tone="info" class="dbg-page-notice">
            Waiting for the first schedule:run. Add this cron entry on the server: <code>{{ data.cron }}</code>
        </Notice>

        <EmptyState v-if="!data.tasks.length" title="No scheduled tasks"
            description="Tasks you add in routes/console.php show up here with their runs, after the next schedule:run." />
        <DataTable v-else :columns="taskColumns">
            <TableRow v-for="task in data.tasks" :key="task.task + task.expression">
                <span class="dbg-cell dbg-cell--mono dbg-cell--strong dbg-cell--truncate" :title="task.task">{{ task.task }}</span>
                <span class="dbg-cell">
                    <span class="dbg-cell__code">{{ task.expression }}</span>
                    <span v-if="task.every" class="dbg-cell__sub">{{ task.every }}</span>
                </span>
                <span class="dbg-cell dbg-cell--num" style="color: var(--color-text-secondary)">{{ task.next || '–' }}</span>
                <span class="dbg-cell dbg-cell--num" style="color: var(--color-text-secondary)">
                    <template v-if="task.last">
                        {{ task.last }}
                        <StatusText :tone="tones[task.last_status] || 'muted'" style="margin-inline-start: var(--spacing-2)">{{ task.last_status }}</StatusText>
                    </template>
                    <template v-else>not yet</template>
                </span>
            </TableRow>
        </DataTable>

        <h2 class="dbg-section-title">Recent runs</h2>
        <Notice v-if="data.error" tone="danger">Couldn't read scheduler runs: {{ data.error }}. Run php artisan migrate to create the debugger tables.</Notice>
        <EmptyState v-else-if="!data.runs.length" title="No runs yet" description="Each task run is recorded here once schedule:run starts it." />
        <div v-else class="dbg-fill dbg-fill__list">
            <DataTable :columns="runColumns">
                <TableRow v-for="run in data.runs" :key="run.id">
                    <span class="dbg-cell dbg-cell--muted dbg-cell--num">{{ run.time }}</span>
                    <span class="dbg-cell dbg-cell--mono dbg-cell--truncate" :title="run.task">{{ run.task }}</span>
                    <span class="dbg-cell dbg-cell--truncate" :title="run.reason">
                        <StatusText :tone="tones[run.status] || 'muted'">{{ run.status }}{{ run.reason ? `, ${run.reason}` : '' }}</StatusText>
                    </span>
                    <span class="dbg-cell dbg-cell--muted dbg-cell--num is-end">{{ run.took || '–' }}</span>
                </TableRow>
            </DataTable>
            <PaginationNav :pagination="data.pagination" />
        </div>
    </template>
</template>
