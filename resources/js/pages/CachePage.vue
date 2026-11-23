<script setup>
import { api } from '../api';
import { config } from '../config';
import { useResource } from '../useResource';
import ActionButton from '../components/ActionButton.vue';
import ActionRow from '../components/ActionRow.vue';
import ConfirmButton from '../components/ConfirmButton.vue';
import InfoList from '../components/InfoList.vue';
import LoadingState from '../components/LoadingState.vue';
import PageTitle from '../components/PageTitle.vue';

const { data, failed } = useResource('/cache');

async function run(key) {
    const result = await api.post(`/cache/${key}`);
    if (result.status) data.value = { ...data.value, status: result.status };
}
</script>

<template>
    <LoadingState v-if="!data" :failed="failed" />
    <template v-else>
        <PageTitle title="Cache" summary="Clear what's stale after a deploy or a config change." />
        <div class="dbg-aside-layout">
            <div class="dbg-box">
                <ActionRow v-for="action in data.actions" :key="action.key" :title="action.title" :description="action.description">
                    <ActionButton variant="secondary" busy-label="Clearing…" :disabled="config.readOnly" :action="() => run(action.key)">Clear</ActionButton>
                </ActionRow>
                <ActionRow title="Rebuild caches" description="Runs optimize. Only for production: afterwards .env edits are ignored until you clear the config cache.">
                    <ConfirmButton label="Rebuild" variant="secondary" title="Rebuild config, route, event and view caches?"
                        body="Changes to .env won't apply until you clear the config cache again. Routes that use closures can make this fail."
                        confirm="Rebuild caches" busy-label="Rebuilding…" confirm-variant="primary" :disabled="config.readOnly" :action="() => run('optimize')" />
                </ActionRow>
            </div>
            <InfoList title="Current state" :items="data.status" />
        </div>
    </template>
</template>
