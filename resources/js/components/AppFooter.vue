<script setup>
import { config } from '../config';
</script>

<template>
    <footer class="dbg-footer">
        <span>Version <b>{{ config.version }}</b></span>
        <span class="dbg-footer__sep" aria-hidden="true">·</span>
        <!-- To the people who made this, kept low-key on purpose. -->
        <a class="dbg-footer__by" href="https://soara.id" target="_blank" rel="noopener" title="soara.id">Say hi</a>
    </footer>
</template>
