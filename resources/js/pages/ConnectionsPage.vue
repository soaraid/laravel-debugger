<script setup>
import { computed } from 'vue';
import { api } from '../api';
import { config } from '../config';
import { useResource } from '../useResource';
import ActionButton from '../components/ActionButton.vue';
import CodeBlock from '../components/CodeBlock.vue';
import DataTable from '../components/DataTable.vue';
import LoadingState from '../components/LoadingState.vue';
import PageTitle from '../components/PageTitle.vue';
import StatusText from '../components/StatusText.vue';
import TableRow from '../components/TableRow.vue';

const { data, failed } = useResource('/connections');

const summary = computed(() => 'Whether the server can reach the APIs this app depends on.'
    + (data.value?.last_checked ? ` Last checked ${data.value.last_checked}.` : ''));

const columns = [
    { label: 'Service', width: 'minmax(0, 1fr)' },
    { label: 'Result', width: '180px' },
    { label: 'Response', width: '96px', end: true },
    { label: '', width: '72px' },
];

const example = `'connections' => [
    'midtrans' => [
        'url'     => 'https://api.midtrans.com/v2/ping',
        'headers' => ['Authorization' => 'Basic '.env('MIDTRANS_KEY')],
        'timeout' => 5,
    ],
],`;

async function ping(path) {
    const result = await api.post(path);
    data.value = { ...data.value, services: result.services, last_checked: result.last_checked };
}
</script>

<template>
    <LoadingState v-if="!data" :failed="failed" />
    <template v-else>
        <PageTitle title="Connections" :summary="summary">
            <template v-if="data.services.length" #actions>
                <ActionButton variant="secondary" busy-label="Pinging…" :disabled="config.readOnly" :action="() => ping('/connections/ping-all')">Ping all</ActionButton>
            </template>
        </PageTitle>

        <div v-if="!data.services.length" class="dbg-stack" style="max-width: 560px; margin: var(--spacing-8) auto 0">
            <p style="font-weight: 500; font-size: var(--text-base)">No services yet</p>
            <p class="dbg-cell--muted">Add the APIs you want to ping to config/debugger.php. They show up here with a Ping button.</p>
            <CodeBlock>{{ example }}</CodeBlock>
        </div>
        <template v-else>
            <DataTable :columns="columns">
                <TableRow v-for="service in data.services" :key="service.key">
                    <span class="dbg-cell">
                        <span class="dbg-cell--strong">{{ service.name }}</span>
                        <span class="dbg-cell__sub" style="font-family: var(--font-mono)">{{ service.endpoint }}</span>
                    </span>
                    <span class="dbg-cell">
                        <StatusText v-if="service.result" :tone="service.result.tone">{{ service.result.label }}</StatusText>
                        <span v-else class="dbg-cell--muted">not checked</span>
                    </span>
                    <span class="dbg-cell dbg-cell--num is-end" style="color: var(--color-text-secondary)">
                        {{ service.result && service.result.ms !== null ? `${service.result.ms.toLocaleString()} ms` : '–' }}
                    </span>
                    <span class="dbg-cell dbg-cell--actions">
                        <ActionButton :disabled="config.readOnly" :action="() => ping(`/connections/${service.key}/ping`)">Ping</ActionButton>
                    </span>
                    <template v-if="service.result && service.result.tone !== 'ok'" #detail>
                        <code v-if="service.result.error">{{ service.result.error }}</code>
                        <p v-if="service.result.hint">{{ service.result.hint }}</p>
                    </template>
                </TableRow>
            </DataTable>
            <p class="dbg-note">Services come from config/debugger.php. Tokens stay in .env and are never shown.</p>
        </template>
    </template>
</template>
