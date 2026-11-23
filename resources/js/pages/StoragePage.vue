<script setup>
import { ref } from 'vue';
import { api, ValidationError } from '../api';
import { config } from '../config';
import { useResource } from '../useResource';
import ActionButton from '../components/ActionButton.vue';
import ConfirmButton from '../components/ConfirmButton.vue';
import DataTable from '../components/DataTable.vue';
import DButton from '../components/DButton.vue';
import EmptyState from '../components/EmptyState.vue';
import InfoList from '../components/InfoList.vue';
import LoadingState from '../components/LoadingState.vue';
import Notice from '../components/Notice.vue';
import PageTitle from '../components/PageTitle.vue';
import StatusText from '../components/StatusText.vue';
import TableRow from '../components/TableRow.vue';

const { data, failed, reload } = useResource('/storage');
const input = ref(null);
const over = ref(false);
const uploading = ref(false);
const uploadError = ref(null);
const probes = ref(null);

const columns = [
    { label: 'File', width: 'minmax(0, 1fr)' },
    { label: 'Size', width: '90px' },
    { label: 'Uploaded', width: '130px' },
    { label: '', width: '140px' },
];

async function upload(files) {
    if (!files?.length || config.readOnly) return;
    const body = new FormData();
    body.append('file', files[0]);
    uploading.value = true;
    uploadError.value = null;
    try {
        await api.post('/storage', body);
        await reload();
    } catch (error) {
        if (error instanceof ValidationError) uploadError.value = error.errors.file?.[0];
    } finally {
        uploading.value = false;
        if (input.value) input.value.value = '';
    }
}

function drop(event) {
    over.value = false;
    upload(event.dataTransfer.files);
}

const remove = (file) => api.delete('/storage', { name: file.name }).then(reload);
const check = async () => (probes.value = (await api.post('/storage/check')).probes);
</script>

<template>
    <LoadingState v-if="!data" :failed="failed" />
    <template v-else>
        <PageTitle title="Storage" summary="Upload a file to check that the upload disk works." />
        <div class="dbg-aside-layout">
            <div style="min-width: 0">
                <div class="dbg-drop" :class="{ 'is-over': over }" @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="drop">
                    <span>Drop a file here or choose one. Max {{ data.max_mb }} MB. Files go to a private folder.</span>
                    <input id="upload-file" ref="input" type="file" class="dbg-sr-only" :disabled="config.readOnly || uploading" @change="upload($event.target.files)">
                    <label for="upload-file" :class="['dbg-btn', 'dbg-btn--primary']" :aria-disabled="config.readOnly || uploading ? 'true' : null">
                        <span v-if="uploading" class="dbg-btn__spinner" aria-hidden="true"></span>
                        {{ uploading ? 'Uploading…' : 'Choose file' }}
                    </label>
                </div>
                <Notice v-if="uploadError" tone="danger" style="margin: calc(var(--spacing-4) * -1) 0 var(--spacing-6)">{{ uploadError }}</Notice>

                <h2 class="dbg-info-list__title">Test uploads</h2>
                <Notice v-if="data.error" tone="danger">Couldn't list files on the {{ data.disk }} disk: {{ data.error }}</Notice>
                <EmptyState v-else-if="!data.files.length" title="No test uploads"
                    :description="`Files you upload here are kept in ${data.folder} on the ${data.disk} disk until you delete them.`" />
                <DataTable v-else :columns="columns">
                    <TableRow v-for="file in data.files" :key="file.name">
                        <span class="dbg-cell dbg-cell--mono dbg-cell--truncate" :title="file.name">{{ file.name }}</span>
                        <span class="dbg-cell dbg-cell--muted dbg-cell--num">{{ file.size }}</span>
                        <span class="dbg-cell dbg-cell--muted dbg-cell--num">{{ file.uploaded }}</span>
                        <span class="dbg-cell dbg-cell--actions">
                            <a :href="file.url" target="_blank" rel="noopener" class="dbg-btn dbg-btn--ghost">Open</a>
                            <ConfirmButton label="Delete" :title="`Delete ${file.name}?`" body="It's removed from the disk and can't be recovered."
                                confirm="Delete file" busy-label="Deleting…" :disabled="config.readOnly" :action="() => remove(file)" />
                        </span>
                    </TableRow>
                </DataTable>
            </div>

            <InfoList title="Disks" :items="{ 'Upload disk': data.disk, Folder: data.folder, ...(data.free ? { 'Free space': data.free } : {}) }">
                <div v-for="probe in probes || []" :key="probe.disk" class="dbg-info">
                    <dt>{{ probe.disk }}</dt>
                    <dd>
                        <template v-if="probe.ok">ok · {{ probe.time }}</template>
                        <template v-else>
                            <StatusText tone="danger">failed</StatusText>
                            <span class="dbg-cell__sub" style="white-space: normal">{{ probe.error }}</span>
                        </template>
                    </dd>
                </div>
                <template #after>
                    <div style="margin-top: var(--spacing-3)">
                        <ActionButton variant="secondary" busy-label="Checking…" :disabled="config.readOnly" :action="check">
                            {{ probes ? 'Check again' : 'Check disks' }}
                        </ActionButton>
                    </div>
                    <p class="dbg-note" style="margin-top: var(--spacing-2)">Writes, reads and deletes a small file on each disk.</p>
                </template>
            </InfoList>
        </div>
    </template>
</template>
