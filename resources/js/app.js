import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import { config } from './config';
import { showNotice } from './notice';

import CachePage from './pages/CachePage.vue';
import ConnectionsPage from './pages/ConnectionsPage.vue';
import LogsPage from './pages/LogsPage.vue';
import MailPage from './pages/MailPage.vue';
import MissingPage from './pages/MissingPage.vue';
import OverviewPage from './pages/OverviewPage.vue';
import QueuePage from './pages/QueuePage.vue';
import SchedulerPage from './pages/SchedulerPage.vue';
import StoragePage from './pages/StoragePage.vue';
import UsersPage from './pages/UsersPage.vue';

// One bundle on purpose: the pages are small, so loading them up front
// costs less than an extra request on every first visit to a tab.
const pages = {
    overview: OverviewPage,
    logs: LogsPage,
    queue: QueuePage,
    scheduler: SchedulerPage,
    cache: CachePage,
    mail: MailPage,
    storage: StoragePage,
    connections: ConnectionsPage,
    users: UsersPage,
};

const modules = Object.keys(config.modules || {});

const router = createRouter({
    history: createWebHistory(config.base),
    routes: [
        { path: '/', redirect: `/${modules[0]}` },
        // Pages built around one long list fill the screen: the rows scroll
        // inside the table and the title and pagination stay in view.
        ...modules.map((key) => ({ path: `/${key}`, name: key, component: pages[key], meta: { title: config.modules[key], fill: ['logs', 'queue', 'scheduler', 'users'].includes(key) } })),
        { path: '/:pathMatch(.*)*', name: 'missing', component: MissingPage, meta: { title: 'Not found' } },
    ],
    // Back to the top for a new section or another page of the same list.
    scrollBehavior: (to, from, saved) => saved || (to.name !== from.name || to.query.page !== from.query.page ? { top: 0 } : false),
});

router.afterEach((to, from) => {
    document.title = `${to.meta.title} · ${config.app} debugger`;

    // A notice belongs to the page it was shown on.
    if (from.name && to.name !== from.name) showNotice(null);
});

// Move focus to the new page's heading after navigating, for screen readers and keyboards.
router.afterEach(async (to, from) => {
    if (!from.name || to.name === from.name) return;
    await router.isReady();
    requestAnimationFrame(() => document.getElementById('main')?.focus({ preventScroll: true }));
});

if (config.notice) showNotice(config.notice);

createApp(App).use(router).mount('#debugger');
