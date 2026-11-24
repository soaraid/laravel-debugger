<script setup>
import { ref, useId } from 'vue';
import DButton from './DButton.vue';

// A button that asks first, in a native <dialog>. For actions that can't be
// undone: the title names the action and the object, the body says what is
// lost, the confirm label is verb + object.
const props = defineProps({
    label: { type: String, required: true },
    title: { type: String, required: true },
    body: { type: String, default: null },
    confirm: { type: String, required: true },
    busyLabel: { type: String, default: null },
    variant: { type: String, default: 'ghost-danger' },
    confirmVariant: { type: String, default: 'danger' },
    disabled: { type: Boolean, default: false },
    action: { type: Function, required: true },
    // false: no button of its own; open it from elsewhere with open().
    trigger: { type: Boolean, default: true },
});

const dialog = ref(null);
const busy = ref(false);
const titleId = useId();

defineExpose({ open: () => dialog.value.showModal() });

async function run() {
    busy.value = true;
    try {
        await props.action();
        dialog.value?.close();
    } catch {
        dialog.value?.close();
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <DButton v-if="trigger" :variant="variant" :disabled="disabled" @click="dialog.showModal()">{{ label }}</DButton>
    <dialog ref="dialog" class="dbg-dialog" :aria-labelledby="titleId">
        <form method="dialog" @submit.prevent="run">
            <h2 :id="titleId">{{ title }}</h2>
            <p v-if="body">{{ body }}</p>
            <div class="dbg-dialog__buttons">
                <DButton variant="secondary" autofocus @click="dialog.close()">Cancel</DButton>
                <DButton :variant="confirmVariant" type="submit" :loading="busy">{{ busy && busyLabel ? busyLabel : confirm }}</DButton>
            </div>
        </form>
    </dialog>
</template>
