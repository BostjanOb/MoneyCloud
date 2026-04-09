<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { show as providerShow } from '@/actions/App/Http/Controllers/InvestmentProviderController';
import {
    create as symbolCreate,
    destroy as symbolDestroy,
    edit as symbolEdit,
    index as symbolIndex,
} from '@/actions/App/Http/Controllers/InvestmentSymbolController';
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
import { formatSlovenianNumber } from '@/lib/utils';

type SymbolRow = {
    id: number;
    type: string;
    type_label: string;
    symbol: string;
    isin: string | null;
    taxable: boolean;
    price_source: string;
    current_price: string;
};

type Props = {
    symbols: SymbolRow[];
};

defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Investicije',
                href: providerShow.url('ibkr'),
            },
            {
                title: 'Simboli',
                href: symbolIndex.url(),
            },
        ],
    },
});

function formatMoney(value: string | number): string {
    return `${formatSlovenianNumber(value)} €`;
}

function deleteSymbol(symbol: SymbolRow): void {
    if (!confirm(`Ste prepričani, da želite izbrisati simbol ${symbol.symbol}?`)) {
        return;
    }

    router.delete(symbolDestroy.url(symbol.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Investicije – Simboli" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                title="Investicijski simboli"
                description="Centralni šifrant za ETF-je, delnice, kripto in obveznice."
            />
            <Button as-child size="sm">
                <Link :href="symbolCreate.url()">Dodaj simbol</Link>
            </Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Pregled simbolov</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table v-if="symbols.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Simbol</TableHead>
                            <TableHead>Tip</TableHead>
                            <TableHead>ISIN</TableHead>
                            <TableHead>Obdavčljivo</TableHead>
                            <TableHead>Vir cene</TableHead>
                            <TableHead numeric class="text-right">
                                Trenutna cena
                            </TableHead>
                            <TableHead class="text-right">Akcije</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="symbol in symbols" :key="symbol.id">
                            <TableCell class="font-medium">
                                {{ symbol.symbol }}
                            </TableCell>
                            <TableCell>{{ symbol.type_label }}</TableCell>
                            <TableCell>{{ symbol.isin ?? '–' }}</TableCell>
                            <TableCell>
                                {{ symbol.taxable ? 'Da' : 'Ne' }}
                            </TableCell>
                            <TableCell>{{ symbol.price_source }}</TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(symbol.current_price) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button as-child variant="ghost" size="sm">
                                        <Link :href="symbolEdit.url(symbol.id)">
                                            Uredi
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deleteSymbol(symbol)"
                                    >
                                        Briši
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
                    Ni še definiranih simbolov.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
