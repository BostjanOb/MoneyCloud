<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    create as personCreate,
    destroy as personDestroy,
    edit as personEdit,
    index as personIndex,
} from '@/actions/App/Http/Controllers/PersonController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

type PersonRow = {
    id: number;
    slug: string;
    name: string;
    is_active: boolean;
    sort_order: number;
};

type Props = {
    people: PersonRow[];
};

defineProps<Props>();

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

function deactivatePerson(person: PersonRow): void {
    if (!confirm(`Deaktiviram osebo ${person.name}?`)) {
        return;
    }

    router.delete(personDestroy.url(person.slug), { preserveScroll: true });
}
</script>

<template>
    <Head title="Osebe" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                title="Osebe"
                description="Osebe za plače, varčevanje in druge osebne finance."
            />
            <Button as-child size="sm">
                <Link :href="personCreate.url()">Nova oseba</Link>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Pregled oseb</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table v-if="people.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Ime</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead numeric class="text-right">
                                Vrstni red
                            </TableHead>
                            <TableHead class="text-right">Akcije</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="person in people" :key="person.id">
                            <TableCell class="font-medium">
                                {{ person.name }}
                            </TableCell>
                            <TableCell>{{ person.slug }}</TableCell>
                            <TableCell>
                                {{ person.is_active ? 'Aktivna' : 'Neaktivna' }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ person.sort_order }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button as-child variant="ghost" size="sm">
                                        <Link
                                            :href="personEdit.url(person.slug)"
                                        >
                                            Uredi
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="person.is_active"
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deactivatePerson(person)"
                                    >
                                        Deaktiviraj
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p
                    v-else
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    Ni še dodanih oseb.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
