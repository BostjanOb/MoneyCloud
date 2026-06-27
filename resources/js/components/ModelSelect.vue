<script setup lang="ts">
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AdvisorModelGroup } from '@/lib/advisor';

defineProps<{
    modelValue: string;
    models: AdvisorModelGroup[];
    disabled?: boolean;
}>();

defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <Select
        :model-value="modelValue"
        :disabled="disabled"
        @update:model-value="$emit('update:modelValue', String($event))"
    >
        <SelectTrigger size="sm" class="w-48">
            <SelectValue />
        </SelectTrigger>
        <SelectContent>
            <SelectGroup v-for="group in models" :key="group.provider">
                <SelectLabel>{{ group.provider }}</SelectLabel>
                <SelectItem
                    v-for="model in group.models"
                    :key="model.value"
                    :value="model.value"
                >
                    {{ model.label }}
                </SelectItem>
            </SelectGroup>
        </SelectContent>
    </Select>
</template>
