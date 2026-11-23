<script setup>
import { computed, ref } from 'vue';
import CodeBlock from './CodeBlock.vue';
import DButton from './DButton.vue';

// The opened part of a log row: the exception (or the full message),
// extra context, and the stack trace with vendor frames folded away.
const props = defineProps({ entry: { type: Object, required: true } });
const showVendor = ref(false);

const vendorCount = computed(() => props.entry.stack.filter((f) => f.vendor).length);
const frames = computed(() => props.entry.stack.filter((f) => showVendor.value || !f.vendor));
</script>

<template>
    <div v-if="entry.exception" class="dbg-exception">
        <p class="dbg-exception__class">{{ entry.exception.class }}</p>
        <p class="dbg-exception__message">{{ entry.exception.message }}</p>
        <p class="dbg-exception__where">in {{ entry.exception.file }}:{{ entry.exception.line }}</p>
    </div>
    <CodeBlock v-else wrap>{{ entry.message }}</CodeBlock>

    <CodeBlock v-if="entry.data">{{ entry.data }}</CodeBlock>
    <CodeBlock v-if="entry.context" wrap>{{ entry.context }}</CodeBlock>

    <div v-if="entry.stack.length">
        <ol class="dbg-frames">
            <li v-for="frame in frames" :key="frame.number" :class="{ 'is-vendor': frame.vendor }">
                <span class="dbg-frames__number">#{{ frame.number }}</span>
                <span class="dbg-frames__text">
                    <span v-if="frame.file" class="dbg-frames__file">{{ frame.file }}<template v-if="frame.line">:{{ frame.line }}</template></span>
                    <span class="dbg-frames__call">{{ frame.call }}</span>
                </span>
            </li>
        </ol>
        <DButton v-if="vendorCount" variant="ghost" style="margin-top: var(--spacing-2)" @click="showVendor = !showVendor">
            {{ showVendor ? 'Hide vendor frames' : `Show ${vendorCount} vendor ${vendorCount === 1 ? 'frame' : 'frames'}` }}
        </DButton>
    </div>

    <p class="dbg-cell--muted" style="font-size: var(--text-xs)">{{ entry.full_time }} · {{ entry.environment }}</p>
</template>
