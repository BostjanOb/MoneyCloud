<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    index as personIndex,
    store as personStore,
    update as personUpdate,
} from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type PersonFormData = {
    id: number;
    slug: string;
    name: string;
    is_active: boolean;
    sort_order: number;
};

type Props = {
    person: PersonFormData | null;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Osebe',
                href: personIndex.url(),
            },
        ],
    },
});

const isEditing = computed(() => props.person !== null);

const form = useForm({
    name: props.person?.name ?? '',
    slug: props.person?.slug ?? '',
    is_active: props.person?.is_active ?? true,
    sort_order: String(props.person?.sort_order ?? 0),
});

function submitPerson(): void {
    form.transform((data) => ({
        ...data,
        sort_order: Number(data.sort_order),
    }));

    if (props.person) {
        form.put(personUpdate.url(props.person.slug), {
            preserveScroll: true,
        });

        return;
    }

    form.post(personStore.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="isEditing ? 'Uredi osebo' : 'Nova oseba'" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                :title="isEditing ? 'Uredi osebo' : 'Nova oseba'"
                :description="
                    isEditing
                        ? 'Posodobite ime, slug, status in vrstni red.'
                        : 'Dodajte osebo za uporabo v osebnih financah.'
                "
            />
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="personIndex.url()">Nazaj na osebe</Link>
                </Button>
                <Button @click="submitPerson" :disabled="form.processing">
                    {{ isEditing ? 'Shrani spremembe' : 'Shrani osebo' }}
                </Button>
            </div>
        </div>

        <form
            class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]"
            @submit.prevent="submitPerson"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Osnovni podatki</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="space-y-1.5">
                        <Label for="person-name">Ime</Label>
                        <Input id="person-name" v-model="form.name" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="person-slug">Slug</Label>
                        <Input
                            id="person-slug"
                            v-model="form.slug"
                            placeholder="Samodejno iz imena"
                        />
                        <InputError :message="form.errors.slug" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Nastavitve</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="space-y-1.5">
                        <Label for="person-sort-order">Vrstni red</Label>
                        <Input
                            id="person-sort-order"
                            v-model="form.sort_order"
                            type="number"
                            min="0"
                            step="1"
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>

                    <Label
                        for="person-is-active"
                        class="flex items-center gap-3 rounded-lg border p-3"
                    >
                        <Checkbox
                            id="person-is-active"
                            v-model="form.is_active"
                        />
                        <span>Oseba je aktivna</span>
                    </Label>
                    <InputError :message="form.errors.is_active" />
                </CardContent>
            </Card>
        </form>
    </div>
</template>
