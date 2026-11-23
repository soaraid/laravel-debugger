<script setup>
import { computed } from 'vue';
import { useResource } from '../useResource';
import InfoList from '../components/InfoList.vue';
import LoadingState from '../components/LoadingState.vue';
import PageTitle from '../components/PageTitle.vue';
import StatusText from '../components/StatusText.vue';

const { data, failed } = useResource('/overview');

const summary = computed(() => {
    const n = data.value?.attention || 0;
    const head = n === 0 ? 'Nothing needs a look.' : `${n} ${n === 1 ? 'thing needs' : 'things need'} a look.`;
    return `${head} Checked at ${data.value?.checked_at}.`;
});
</script>

<template>
    <LoadingState v-if="!data" :failed="failed" />
    <template v-else>
        <PageTitle title="Overview" :summary="summary" />
        <div class="dbg-aside-layout">
            <section>
                <div class="dbg-box">
                    <component :is="row.to ? 'RouterLink' : 'div'" v-for="row in data.rows" :key="row.key"
                        :to="row.to ? { path: `/${row.to}`, query: row.query } : undefined" class="dbg-overview-row">
                        <span class="dbg-overview-row__name">{{ row.name }}</span>
                        <StatusText :tone="row.tone">{{ row.status }}</StatusText>
                        <span class="dbg-overview-row__text" :title="row.text">{{ row.text }}</span>
                        <span v-if="row.link" class="dbg-overview-row__link">{{ row.link }}</span>
                    </component>
                </div>
                <p class="dbg-note">Errors count the last hour of every log file. Pings and test uploads only run when you start them.</p>
            </section>
            <InfoList title="App" :items="data.app" />
        </div>
    </template>
</template>
