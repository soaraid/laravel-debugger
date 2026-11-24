<script setup>
import AppFooter from './components/AppFooter.vue';
import AppHeader from './components/AppHeader.vue';
import Notice from './components/Notice.vue';
import { config } from './config';
import { notice } from './notice';
import { navigation } from './useResource';
</script>

<template>
    <AppHeader />
    <main id="main" class="dbg-main" :class="{ 'is-loading': navigation.loading }" tabindex="-1">
        <Notice v-if="config.readOnly" tone="info" class="dbg-page-notice">
            Read-only: actions are turned off in this environment.
        </Notice>
        <Notice v-if="notice.current" :key="notice.current.text" :tone="notice.current.tone" class="dbg-page-notice">
            {{ notice.current.text }}
        </Notice>
        <RouterView />
    </main>
    <AppFooter />
</template>
