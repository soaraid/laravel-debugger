<script setup>
import { provide, ref } from 'vue';

// One row of <DataTable>. With "expandable", put the row's name in
// <RowToggle> and the extra content in the "detail" slot. Without it, a
// "detail" slot is always shown (an explanation under a failed row).
const props = defineProps({ expandable: { type: Boolean, default: false } });
const open = ref(false);

provide('row', { open, toggle: () => (open.value = !open.value) });

function onClick(event) {
    if (props.expandable && !event.target.closest('a, button, form, dialog, input, select, label')) open.value = !open.value;
}
</script>

<template>
    <div class="dbg-row" :class="{ 'dbg-row--expandable': expandable, 'is-open': open }" role="row">
        <div class="dbg-row__main" @click="onClick"><slot /></div>
        <div v-if="expandable && $slots.detail && open" class="dbg-row__detail"><slot name="detail" /></div>
        <div v-else-if="!expandable && $slots.detail" class="dbg-row__detail dbg-row__detail--note"><slot name="detail" /></div>
    </div>
</template>
