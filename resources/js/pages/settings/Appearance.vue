<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import { edit } from '@/routes/appearance';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Videz',
                href: edit(),
            },
        ],
    },
});
</script>

<template>
    <Head title="Videz" />

    <h1 class="sr-only">Videz</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Nastavitve videza"
            description="Prilagodite videz svojega računa"
        />
        <AppearanceTabs />
    </div>
</template>
