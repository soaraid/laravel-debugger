<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { api } from '../api';
import { config } from '../config';
import { useResource } from '../useResource';
import ActionButton from '../components/ActionButton.vue';
import DataTable from '../components/DataTable.vue';
import DButton from '../components/DButton.vue';
import EmptyState from '../components/EmptyState.vue';
import LoadingState from '../components/LoadingState.vue';
import Notice from '../components/Notice.vue';
import PageTitle from '../components/PageTitle.vue';
import PaginationNav from '../components/PaginationNav.vue';
import TableRow from '../components/TableRow.vue';

const route = useRoute();
const router = useRouter();
const { data, failed } = useResource('/users');
const search = ref(route.query.q || '');
let timer;

watch(() => route.query.q, (q) => (search.value = q || ''));

// Search as you type, a moment after the last keystroke.
watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        if ((route.query.q || '') !== value) router.replace({ query: { q: value || undefined } });
    }, 250);
});

const columns = computed(() => [
    { label: 'User', width: 'minmax(0, 1.6fr)' },
    ...(data.value?.show_role ? [{ label: 'Role', width: 'minmax(0, 1fr)' }] : []),
    ...(data.value?.show_last_active ? [{ label: 'Last active', width: '140px' }] : []),
    { label: '', width: '140px' },
]);

async function impersonate(user) {
    const result = await api.post(`/users/${user.id}/impersonate`);
    if (result.redirect) window.location.assign(result.redirect);
}

const sessionColumns = [{ label: 'Time', width: '110px' }, { label: 'What happened', width: 'minmax(0, 1fr)' }];
</script>

<template>
    <LoadingState v-if="!data" :failed="failed" />
    <template v-else>
        <PageTitle title="Users" summary="Log in as someone to see the app the way they see it. You can switch back at any time." />
        <Notice v-if="!data.enabled" tone="info" class="dbg-page-notice">Impersonation is turned off (debugger.impersonation.enabled).</Notice>

        <Notice v-if="data.error" tone="danger">Couldn't list users: {{ data.error }}</Notice>
        <template v-else>
            <form class="dbg-toolbar" role="search" @submit.prevent>
                <label class="dbg-sr-only" for="user-search">Search users</label>
                <input id="user-search" v-model="search" class="dbg-input" type="search" placeholder="Search by name or email, e.g. budi">
            </form>

            <EmptyState v-if="!data.users.length && route.query.q" :title="`No users match “${route.query.q}”`" description="Try part of the name or the email address.">
                <template #action><DButton variant="secondary" :to="{ query: {} }">Clear search</DButton></template>
            </EmptyState>
            <EmptyState v-else-if="!data.users.length" title="No users yet" description="Users of the app show up here once they exist." />
            <template v-else>
                <DataTable :columns="columns">
                    <TableRow v-for="user in data.users" :key="user.id">
                        <span class="dbg-cell">
                            <span class="dbg-cell--strong">{{ user.name }}</span>
                            <span v-if="user.email" class="dbg-cell__sub">{{ user.email }}</span>
                        </span>
                        <span v-if="data.show_role" class="dbg-cell" style="color: var(--color-text-secondary)">{{ user.role || '–' }}</span>
                        <span v-if="data.show_last_active" class="dbg-cell dbg-cell--muted dbg-cell--num">{{ user.last_active || '–' }}</span>
                        <span class="dbg-cell dbg-cell--actions">
                            <span v-if="user.refusal" class="dbg-cell--muted" style="padding-inline-end: var(--spacing-3)">{{ user.refusal }}</span>
                            <ActionButton v-else-if="data.enabled" busy-label="Logging in…" :disabled="config.readOnly" :action="() => impersonate(user)">Log in as</ActionButton>
                        </span>
                    </TableRow>
                </DataTable>
                <PaginationNav :pagination="data.pagination" />
            </template>
        </template>

        <template v-if="data.sessions.length">
            <h2 class="dbg-section-title">Recent sessions</h2>
            <DataTable :columns="sessionColumns">
                <TableRow v-for="entry in data.sessions" :key="entry.id">
                    <span class="dbg-cell dbg-cell--muted dbg-cell--num">{{ entry.time }}</span>
                    <span class="dbg-cell dbg-cell--truncate">{{ entry.text }} <span v-if="entry.after" class="dbg-cell--muted">{{ entry.after }}</span></span>
                </TableRow>
            </DataTable>
        </template>
    </template>
</template>
