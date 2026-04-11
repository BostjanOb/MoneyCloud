<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 64 64"
        :class="className"
        v-bind="$attrs"
    >
        <title>Money Cloud icon</title>
        <desc>Cloud outline with a euro sign in the center.</desc>
        <path
            d="M17.5 48h29.25C54.62 48 61 41.95 61 34.5c0-6.45-4.78-11.89-11.19-13.1C48.72 13.7 41.98 8 33.73 8c-9.54 0-17.38 7.47-18.56 16.97C8.61 26.08 3.75 31.93 3.75 38.95 3.75 43.97 7.81 48 12.79 48h4.71Z"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="4"
        />
        <path
            d="M41 24.5c-1.63-1.67-4.15-2.62-6.92-2.5-5.59.23-10.08 4.72-10.08 10.5 0 5.79 4.49 10.28 10.08 10.5 2.77.12 5.29-.83 6.92-2.5"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-linejoin="round"
            stroke-width="4"
        />
        <path
            d="M19.5 29.5H38"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="4"
        />
        <path
            d="M18.5 35.5H36"
            fill="none"
            stroke="currentColor"
            stroke-linecap="round"
            stroke-width="4"
        />
    </svg>
</template>
