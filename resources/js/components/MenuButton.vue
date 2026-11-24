<script setup>
import { onBeforeUnmount, ref, useId, watch } from 'vue';

// A ⋮ button that opens a small menu of actions for one item (Doop:
// ActionMenu). Closes on Escape, on a click outside, and after an item is
// chosen. Items go in the default slot as <button class="dbg-menu__item">.
defineProps({ label: { type: String, required: true } });

const open = ref(false);
const root = ref(null);
const menuId = useId();

function onOutside(event) {
    if (!root.value?.contains(event.target)) open.value = false;
}

function onKey(event) {
    if (event.key === 'Escape') open.value = false;
}

watch(open, (isOpen) => {
    const method = isOpen ? 'addEventListener' : 'removeEventListener';
    document[method]('pointerdown', onOutside);
    document[method]('keydown', onKey);
});

onBeforeUnmount(() => (open.value = false));
</script>

<template>
    <div ref="root" class="dbg-menu">
        <button type="button" class="dbg-menu__button" :aria-label="label" aria-haspopup="menu" :aria-expanded="open ? 'true' : 'false'"
            :aria-controls="menuId" @click="open = !open">
            <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
                <circle cx="8" cy="3.5" r="1.3" fill="currentColor" />
                <circle cx="8" cy="8" r="1.3" fill="currentColor" />
                <circle cx="8" cy="12.5" r="1.3" fill="currentColor" />
            </svg>
        </button>
        <div v-show="open" :id="menuId" class="dbg-menu__list" role="menu" @click="open = false">
            <slot />
        </div>
    </div>
</template>
