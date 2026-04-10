<script setup lang="ts">
import { Head, Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import { computed, watchEffect } from 'vue';
import { show as providerShow } from '@/actions/App/Http/Controllers/InvestmentProviderController';
import {
    create as providerCreate,
    edit as providerEdit,
    index as providerIndex,
} from '@/actions/App/Http/Controllers/InvestmentProviderSettingsController';
import { index as symbolIndex } from '@/actions/App/Http/Controllers/InvestmentSymbolController';
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

type ProviderRow = {
    id: number;
    slug: string;
    name: string;
    sort_order: number;
    requires_linked_savings_account: boolean;
    linked_savings_account_name: string | null;
    supported_symbol_type_labels: string[];
};

type Props = {
    providers: ProviderRow[];
};

defineProps<Props>();

const page = usePage();
const investmentsHref = computed(() => {
    const firstProvider = page.props.investmentProviders[0];

    return firstProvider
        ? providerShow.url(firstProvider.slug)
        : symbolIndex.url();
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Investicije',
                href: investmentsHref.value,
            },
            {
                title: 'Ponudniki',
                href: providerIndex.url(),
            },
        ],
    });
});

function linkedAccountLabel(provider: ProviderRow): string {
    if (!provider.requires_linked_savings_account) {
        return 'Ni potreben';
    }

    if (provider.linked_savings_account_name === null) {
        return 'Obvezen račun ni nastavljen';
    }

    return provider.linked_savings_account_name;
}
</script>

<template>
    <Head title="Ponudniki" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                title="Ponudniki"
                description="Urejanje ponudnikov investicij, podprtih tipov simbolov in povezav z varčevalnimi računi."
            />
            <Button as-child size="sm">
                <Link :href="providerCreate.url()">Nov ponudnik</Link>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Pregled ponudnikov</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table v-if="providers.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Ime</TableHead>
                            <TableHead>Slug</TableHead>
                            <TableHead>Podprti tipi</TableHead>
                            <TableHead>Povezan račun</TableHead>
                            <TableHead numeric class="text-right">
                                Vrstni red
                            </TableHead>
                            <TableHead class="text-right">Akcije</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="provider in providers"
                            :key="provider.id"
                        >
                            <TableCell class="font-medium">
                                {{ provider.name }}
                            </TableCell>
                            <TableCell>{{ provider.slug }}</TableCell>
                            <TableCell>
                                {{
                                    provider.supported_symbol_type_labels.join(
                                        ', ',
                                    )
                                }}
                            </TableCell>
                            <TableCell>
                                {{ linkedAccountLabel(provider) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ provider.sort_order }}
                            </TableCell>
                            <TableCell class="text-right">
                                <Button as-child variant="ghost" size="sm">
                                    <Link :href="providerEdit.url(provider.id)">
                                        Uredi
                                    </Link>
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p
                    v-else
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    Ni še dodanih ponudnikov.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
