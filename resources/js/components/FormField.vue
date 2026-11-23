<script setup>
import { useId } from 'vue';

// Labelled input or textarea with its validation error under it.
defineProps({
    label: { type: String, required: true },
    type: { type: String, default: 'text' },
    multiline: { type: Boolean, default: false },
    placeholder: { type: String, default: null },
    error: { type: String, default: null },
});
const model = defineModel({ type: String, default: '' });
const id = useId();
</script>

<template>
    <div class="dbg-field" :class="{ 'is-invalid': error }">
        <label class="dbg-field__label" :for="id">{{ label }}</label>
        <textarea v-if="multiline" :id="id" v-model="model" class="dbg-field__input" rows="4" :placeholder="placeholder"
            :aria-invalid="error ? 'true' : null" :aria-describedby="error ? `${id}-error` : null"></textarea>
        <input v-else :id="id" v-model="model" class="dbg-field__input" :type="type" :placeholder="placeholder"
            :aria-invalid="error ? 'true' : null" :aria-describedby="error ? `${id}-error` : null">
        <p v-if="error" :id="`${id}-error`" class="dbg-field__error">{{ error }}</p>
    </div>
</template>
