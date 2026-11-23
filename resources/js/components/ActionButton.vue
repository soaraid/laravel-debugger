<script setup>
import { ref } from 'vue';
import DButton from './DButton.vue';

// A one-click button for reversible actions (retry, clear cache, ping).
const props = defineProps({
    variant: { type: String, default: 'ghost' },
    busyLabel: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    action: { type: Function, required: true },
});

const busy = ref(false);

async function run() {
    busy.value = true;
    try {
        await props.action();
    } catch {
        // The API client already showed a notice.
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <DButton :variant="variant" :disabled="disabled" :loading="busy" @click="run">
        <template v-if="busy && busyLabel">{{ busyLabel }}</template>
        <slot v-else />
    </DButton>
</template>
