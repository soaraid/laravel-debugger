<script setup>
defineProps({ value: { type: String, required: true }, label: { type: String, required: true }, description: { type: String, default: null }, name: { type: String, required: true } });
const model = defineModel({ type: String });
</script>

<template>
    <label class="dbg-radio">
        <input v-model="model" type="radio" :name="name" :value="value">
        <span class="dbg-radio__text">
            <span class="dbg-radio__label">{{ label }}</span>
            <span v-if="description" class="dbg-radio__description">{{ description }}</span>
        </span>
    </label>
</template>
