<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex size-9 items-center justify-center rounded-2xl bg-[linear-gradient(135deg,#0f766e_0%,#10b981_55%,#34d399_100%)] text-white shadow-sm ring-1 ring-black/5 dark:ring-white/10"
    >
        <AppLogoIcon class="size-5 text-white" />
    </div>
    <div class="ml-2 grid flex-1 text-left leading-none">
        <span class="truncate text-sm font-semibold tracking-[-0.02em]"
            >Money Cloud</span
        >
        <span
            class="mt-1 truncate text-[0.65rem] font-semibold uppercase tracking-[0.26em] text-muted-foreground"
            >osebne finance</span
        >
    </div>
</template>
