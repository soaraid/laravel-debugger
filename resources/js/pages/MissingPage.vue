<script setup>
import DButton from '../components/DButton.vue';
import EmptyState from '../components/EmptyState.vue';
</script>

<template>
    <EmptyState title="Page not found" description="This section doesn't exist or is turned off in config/debugger.php.">
        <template #action><DButton variant="secondary" to="/">Go to the debugger</DButton></template>
    </EmptyState>
</template>
