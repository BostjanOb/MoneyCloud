<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    index as statisticsIndex,
    yearlyInvested as statisticsYearlyInvested,
} from '@/actions/App/Http/Controllers/StatisticsController';
import Heading from '@/components/Heading.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table } from '@/components/ui/table';
import { formatSlovenianNumber } from '@/lib/utils';
import { sortYearlyInvestmentRows } from '@/lib/yearlyInvested';

type YearlySymbol = {
    id: number;
    symbol: string;
    type: string;
    type_label: string;
};

type YearlySymbolCell = {
    amount: string;
    quantity: string;
};

type YearlyRow = {
    year: number;
    total_amount: string;
    symbols: Record<string, YearlySymbolCell>;
};

type Props = {
    years: number[];
    symbols: YearlySymbol[];
    rows: YearlyRow[];
    totals: {
        grand_total_amount: string;
        symbols: Record<string, YearlySymbolCell>;
    };
};

const props = defineProps<Props>();

const yearlyRows = computed(() => sortYearlyInvestmentRows(props.rows));

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Statistika',
            href: statisticsIndex.url(),
        },
        {
            title: 'Letni vložki',
            href: statisticsYearlyInvested.url(),
        },
    ],
});

function formatMoney(value: string | number): string {
    return `${formatSlovenianNumber(value)} €`;
}

function formatQuantity(value: string | number): string {
    return new Intl.NumberFormat('sl-SI', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 8,
    }).format(Number(value));
}
</script>

<template>
    <Head title="Letni vložki" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Letni vložki"
            description="Pregled vloženega zneska in količine po letih ter simbolih."
        />

        <Card>
            <CardHeader>
                <CardTitle>Letni pregled vložkov</CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="props.rows.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Ko bodo dodani nakupi, se bo tukaj prikazal letni pregled
                    vložkov.
                </div>

                <div v-else class="space-y-4">
                    <div class="flex flex-wrap gap-4">
                        <Card class="min-w-56 flex-1">
                            <CardHeader class="pb-2">
                                <CardTitle
                                    class="text-sm text-muted-foreground"
                                >
                                    Skupaj vloženo
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p class="text-2xl font-semibold">
                                    {{
                                        formatMoney(
                                            props.totals.grand_total_amount,
                                        )
                                    }}
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    <Table class="min-w-max">
                        <thead>
                            <tr class="border-b">
                                <th
                                    rowspan="2"
                                    class="border-r bg-muted/30 px-3 py-2 text-left font-semibold whitespace-nowrap"
                                >
                                    Leto
                                </th>
                                <th
                                    rowspan="2"
                                    class="border-r bg-emerald-100 px-3 py-2 text-right font-semibold whitespace-nowrap text-emerald-950"
                                >
                                    Skupaj znesek
                                </th>
                                <th
                                    v-for="symbol in props.symbols"
                                    :key="symbol.id"
                                    colspan="2"
                                    class="border-r px-3 py-2 text-center font-semibold whitespace-nowrap"
                                >
                                    <div>{{ symbol.symbol }}</div>
                                    <div
                                        class="mt-1 text-xs font-normal text-muted-foreground"
                                    >
                                        {{ symbol.type_label }}
                                    </div>
                                </th>
                            </tr>
                            <tr class="border-b">
                                <template
                                    v-for="symbol in props.symbols"
                                    :key="`${symbol.id}-headers`"
                                >
                                    <th
                                        class="border-r bg-muted/20 px-3 py-2 text-right font-medium whitespace-nowrap"
                                    >
                                        Znesek
                                    </th>
                                    <th
                                        class="border-r bg-muted/20 px-3 py-2 text-right font-medium whitespace-nowrap"
                                    >
                                        Količina
                                    </th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in yearlyRows"
                                :key="row.year"
                                class="border-b transition-colors hover:bg-muted/50"
                            >
                                <td
                                    class="border-r px-3 py-2 font-semibold whitespace-nowrap"
                                >
                                    {{ row.year }}
                                </td>
                                <td
                                    class="border-r px-3 py-2 text-right whitespace-nowrap"
                                >
                                    {{ formatMoney(row.total_amount) }}
                                </td>
                                <template
                                    v-for="symbol in props.symbols"
                                    :key="`${row.year}-${symbol.id}`"
                                >
                                    <td
                                        class="border-r px-3 py-2 text-right whitespace-nowrap"
                                    >
                                        {{
                                            formatMoney(
                                                row.symbols[String(symbol.id)]
                                                    .amount,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="border-r px-3 py-2 text-right whitespace-nowrap"
                                    >
                                        {{
                                            formatQuantity(
                                                row.symbols[String(symbol.id)]
                                                    .quantity,
                                            )
                                        }}
                                    </td>
                                </template>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t bg-muted/50 font-medium">
                                <td
                                    class="border-r px-3 py-2 font-bold whitespace-nowrap"
                                >
                                    SKUPAJ
                                </td>
                                <td
                                    class="border-r px-3 py-2 text-right font-bold whitespace-nowrap"
                                >
                                    {{
                                        formatMoney(
                                            props.totals.grand_total_amount,
                                        )
                                    }}
                                </td>
                                <template
                                    v-for="symbol in props.symbols"
                                    :key="`${symbol.id}-total`"
                                >
                                    <td
                                        class="border-r px-3 py-2 text-right font-bold whitespace-nowrap"
                                    >
                                        {{
                                            formatMoney(
                                                props.totals.symbols[
                                                    String(symbol.id)
                                                ].amount,
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="border-r px-3 py-2 text-right font-bold whitespace-nowrap"
                                    >
                                        {{
                                            formatQuantity(
                                                props.totals.symbols[
                                                    String(symbol.id)
                                                ].quantity,
                                            )
                                        }}
                                    </td>
                                </template>
                            </tr>
                        </tfoot>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
