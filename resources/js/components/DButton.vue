<script setup>
// variant: primary | secondary | ghost | danger | ghost-danger
// loading: shows a spinner; pass the "-ing" label as the slot, e.g. "Sending…"
defineProps({
    variant: { type: String, default: 'primary' },
    type: { type: String, default: 'button' },
    to: { type: [String, Object], default: null },
    href: { type: String, default: null },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});
</script>

<template>
    <RouterLink v-if="to" :to="to" :class="['dbg-btn', `dbg-btn--${variant}`]"><slot /></RouterLink>
    <a v-else-if="href" :href="href" :class="['dbg-btn', `dbg-btn--${variant}`]"><slot /></a>
    <button v-else :type="type" :class="['dbg-btn', `dbg-btn--${variant}`]" :disabled="disabled || loading" :aria-busy="loading ? 'true' : null">
        <span v-if="loading" class="dbg-btn__spinner" aria-hidden="true"></span>
        <slot />
    </button>
</template>
