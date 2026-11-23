<script setup>
import { reactive, ref, watch } from 'vue';
import { api, ValidationError } from '../api';
import { config } from '../config';
import { useResource } from '../useResource';
import DButton from '../components/DButton.vue';
import FormField from '../components/FormField.vue';
import InfoList from '../components/InfoList.vue';
import LoadingState from '../components/LoadingState.vue';
import PageTitle from '../components/PageTitle.vue';
import RadioOption from '../components/RadioOption.vue';

const { data, failed } = useResource('/mail');
const form = reactive({ to: '', subject: '', body: '', via: 'queue' });
const errors = ref({});
const sending = ref(false);

watch(data, (value) => {
    if (value && !form.subject) Object.assign(form, value.defaults);
}, { immediate: true });

async function send() {
    sending.value = true;
    errors.value = {};
    try {
        await api.post('/mail', { ...form });
    } catch (error) {
        if (error instanceof ValidationError) errors.value = error.errors;
    } finally {
        sending.value = false;
    }
}
</script>

<template>
    <LoadingState v-if="!data" :failed="failed" />
    <template v-else>
        <PageTitle title="Mail" summary="Send a test email to check the mailer settings." />
        <div class="dbg-aside-layout">
            <form class="dbg-form" novalidate @submit.prevent="send">
                <FormField v-model="form.to" label="To" type="email" placeholder="name@example.com" :error="errors.to?.[0]" />
                <p v-if="data.override" class="dbg-cell--muted" style="margin-top: calc(var(--spacing-2) * -1)">
                    Every test email goes to {{ data.override }} (DEBUGGER_MAIL_TO).
                </p>
                <FormField v-model="form.subject" label="Subject" :error="errors.subject?.[0]" />
                <FormField v-model="form.body" label="Message" multiline :error="errors.body?.[0]" />
                <fieldset class="dbg-fieldset">
                    <legend>How to send</legend>
                    <RadioOption v-model="form.via" name="via" value="queue" label="Queue"
                        :description="`Goes through ${data.queue} like real emails. Tests the queue worker too.`" />
                    <RadioOption v-model="form.via" name="via" value="sync" label="Sync"
                        description="Sends right now in this request. Errors show up here straight away." />
                </fieldset>
                <div>
                    <DButton type="submit" :loading="sending" :disabled="config.readOnly">{{ sending ? 'Sending…' : 'Send test email' }}</DButton>
                </div>
            </form>

            <InfoList title="Mailer settings" :items="{ ...data.settings, Worker: data.worker ? `last job ${data.worker}` : 'no job seen yet' }">
                <template #after>
                    <p class="dbg-note" style="margin-top: var(--spacing-3)">Passwords and keys are never shown here.</p>
                </template>
            </InfoList>
        </div>
    </template>
</template>
