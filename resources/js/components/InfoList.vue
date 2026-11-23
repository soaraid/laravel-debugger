<script setup>
// items: { Label: 'value' }
defineProps({ title: { type: String, default: null }, items: { type: Object, default: () => ({}) } });
</script>

<template>
    <section>
        <h2 v-if="title" class="dbg-info-list__title">{{ title }}</h2>
        <dl class="dbg-info-list">
            <div v-for="(value, label) in items" :key="label" class="dbg-info">
                <dt>{{ label }}</dt>
                <dd>{{ value }}</dd>
            </div>
            <slot />
        </dl>
        <slot name="after" />
    </section>
</template>
