<script setup lang="ts">
import { Head, Link, router, setLayoutProps, usePage } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watchEffect } from 'vue';
import { show as providerShow } from '@/actions/App/Http/Controllers/InvestmentProviderController';
import {
    create as symbolCreate,
    destroy as symbolDestroy,
    edit as symbolEdit,
    index as symbolIndex,
    refreshPrices,
} from '@/actions/App/Http/Controllers/InvestmentSymbolController';
import Heading from '@/components/Heading.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    price_source_label: string;
    external_source_id: string | null;
    current_price: string;
    price_synced_at: string | null;
};

type SymbolTypeOption = {
    value: string;
    label: string;
};

type Props = {
    symbols: SymbolRow[];
    refreshableCoinMarketCapCount: number;
    refreshableYfApiCount: number;
    refreshableLjseCount: number;
    typeOptions: SymbolTypeOption[];
    filters: {
        type: string | null;
    };
};

const props = defineProps<Props>();
const ALL_TYPES_VALUE = '__all__';

const page = usePage();
const isRefreshing = ref(false);
const investmentsHref = computed(() => {
    const firstProvider = page.props.investmentProviders[0];

    return firstProvider
        ? providerShow.url(firstProvider.slug)
        : symbolIndex.url();
});
const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            status?: string;
            error?: string;
        },
);
const selectedType = computed(() => props.filters.type ?? ALL_TYPES_VALUE);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Investicije',
                href: investmentsHref.value,
            },
            {
                title: 'Simboli',
                href: symbolIndex.url(),
            },
        ],
    });
});

function formatMoney(value: string | number): string {
    return `${formatSlovenianNumber(value)} €`;
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('sl-SI', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function updateTypeFilter(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    router.get(
        symbolIndex.url({
            query:
                value === ALL_TYPES_VALUE
                    ? {}
                    : {
                          type: value,
                      },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function deleteSymbol(symbol: SymbolRow): void {
    if (
        !confirm(`Ste prepričani, da želite izbrisati simbol ${symbol.symbol}?`)
    ) {
        return;
    }

    router.delete(symbolDestroy.url(symbol.id), { preserveScroll: true });
}

function triggerPriceRefresh(source: 'coinmarketcap' | 'yfapi' | 'ljse'): void {
    isRefreshing.value = true;

    router.post(
        refreshPrices.url(source),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                isRefreshing.value = false;
            },
        },
    );
}
</script>

<template>
    <Head title="Investicije – Simboli" />

    <div class="flex flex-col gap-6 p-4">
        <Alert v-if="flash.status">
            <AlertTitle>Osvežitev zaključena</AlertTitle>
            <AlertDescription>{{ flash.status }}</AlertDescription>
        </Alert>

        <Alert v-if="flash.error" variant="destructive">
            <AlertTitle>Osvežitev ni uspela</AlertTitle>
            <AlertDescription>{{ flash.error }}</AlertDescription>
        </Alert>

        <div class="flex items-center justify-between gap-4">
            <Heading
                title="Investicijski simboli"
                description="Centralni šifrant za ETF-je, delnice, kripto in obveznice."
            />
            <div class="flex gap-2">
                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button
                            variant="outline"
                            size="sm"
                            :disabled="
                                (props.refreshableCoinMarketCapCount === 0 &&
                                    props.refreshableYfApiCount === 0 &&
                                    props.refreshableLjseCount === 0) ||
                                isRefreshing
                            "
                        >
                            Osveži cene
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem
                            :disabled="
                                props.refreshableCoinMarketCapCount === 0 ||
                                isRefreshing
                            "
                            @click="triggerPriceRefresh('coinmarketcap')"
                        >
                            CoinMarketCap (kripto)
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            :disabled="
                                props.refreshableYfApiCount === 0 ||
                                isRefreshing
                            "
                            @click="triggerPriceRefresh('yfapi')"
                        >
                            YF API (delnice, ETF-ji)
                        </DropdownMenuItem>
                        <DropdownMenuItem
                            :disabled="
                                props.refreshableLjseCount === 0 || isRefreshing
                            "
                            @click="triggerPriceRefresh('ljse')"
                        >
                            LJSE (delnice, obveznice)
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
                <Button as-child size="sm">
                    <Link :href="symbolCreate.url()">Dodaj simbol</Link>
                </Button>
            </div>
        </div>

        <Card>
            <CardHeader
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <CardTitle>Pregled simbolov</CardTitle>
                <div class="w-full sm:max-w-xs">
                    <Label for="symbol-type-filter">Filtriraj po tipu</Label>
                    <Select
                        :model-value="selectedType"
                        @update:model-value="updateTypeFilter"
                    >
                        <SelectTrigger
                            id="symbol-type-filter"
                            class="mt-1.5 w-full"
                        >
                            <SelectValue placeholder="Vsi tipi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="ALL_TYPES_VALUE">
                                Vsi tipi
                            </SelectItem>
                            <SelectItem
                                v-for="option in typeOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
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
                            <TableCell>
                                <div class="flex flex-col">
                                    <span>{{ symbol.price_source_label }}</span>
                                    <span
                                        v-if="symbol.external_source_id"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ symbol.external_source_id }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell numeric class="text-right">
                                <div class="flex flex-col items-end">
                                    <span>{{
                                        formatMoney(symbol.current_price)
                                    }}</span>
                                    <span
                                        v-if="symbol.price_synced_at"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{
                                            `Nazadnje osveženo ${formatDateTime(symbol.price_synced_at)}`
                                        }}
                                    </span>
                                </div>
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
