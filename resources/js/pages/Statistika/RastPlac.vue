<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import {
    index as statisticsIndex,
    paycheckGrowth as statisticsPaycheckGrowth,
} from '@/actions/App/Http/Controllers/StatisticsController';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Table } from '@/components/ui/table';
import {
    buildPaycheckGrowthSummary,
    displayedPaycheckGrowthSeries
    
    
    
} from '@/lib/paycheckGrowth';
import type {PaycheckGrowthRow, PaycheckGrowthSeries, PaycheckGrowthSummary} from '@/lib/paycheckGrowth';
import { formatSlovenianNumber } from '@/lib/utils';

type PersonFilter = {
    label: string;
    value: string;
};

type Props = {
    filters: PersonFilter[];
    selectedPerson: string;
    includeBonusesDefault: boolean;
    rows: PaycheckGrowthRow[];
    chartSeries: PaycheckGrowthSeries[];
    summary: PaycheckGrowthSummary;
};

const props = defineProps<Props>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Statistika',
            href: statisticsIndex.url(),
        },
        {
            title: 'Rast plač',
            href: statisticsPaycheckGrowth.url(),
        },
    ],
});

const includeBonuses = ref(props.includeBonusesDefault);

const chartDimensions = {
    width: 900,
    height: 320,
    paddingTop: 20,
    paddingRight: 20,
    paddingBottom: 40,
    paddingLeft: 56,
};

const visibleSeries = computed(() =>
    displayedPaycheckGrowthSeries(props.chartSeries, includeBonuses.value),
);

const displayedSummary = computed(() =>
    buildPaycheckGrowthSummary(props.rows, includeBonuses.value),
);

const chartMaxValue = computed(() =>
    Math.max(0, ...visibleSeries.value.flatMap((series) => series.values)),
);

const chartInnerWidth =
    chartDimensions.width -
    chartDimensions.paddingLeft -
    chartDimensions.paddingRight;
const chartInnerHeight =
    chartDimensions.height -
    chartDimensions.paddingTop -
    chartDimensions.paddingBottom;

const chartLines = computed(() =>
    visibleSeries.value.map((series) => ({
        ...series,
        path: buildPath(series.values),
        points: series.values.map((value, index) => ({
            value,
            x: xPosition(index, series.values.length),
            y: yPosition(value),
            isPartial: props.rows[index]?.is_partial ?? false,
        })),
    })),
);

const chartGridLines = computed(() => {
    const lineCount = 4;

    return Array.from({ length: lineCount + 1 }, (_, index) => {
        const ratio = index / lineCount;
        const value = chartMaxValue.value * (1 - ratio);

        return {
            y: chartDimensions.paddingTop + chartInnerHeight * ratio,
            label: formatMoney(value),
        };
    });
});

const summaryCards = computed(() => {
    const latestRow = props.rows.at(-1);
    const netValue = latestRow
        ? includeBonuses.value
            ? latestRow.net_with_bonuses
            : latestRow.net
        : null;
    const grossValue = latestRow
        ? includeBonuses.value
            ? latestRow.gross_with_bonuses
            : latestRow.gross
        : null;

    return [
        {
            label: includeBonuses.value ? 'Neto dohodek z bonusi' : 'Neto dohodek',
            value: netValue,
            changeAmount: displayedSummary.value.net_change_amount,
            changePercentage: displayedSummary.value.net_change_percentage,
        },
        {
            label: includeBonuses.value ? 'Bruto dohodek z bonusi' : 'Bruto dohodek',
            value: grossValue,
            changeAmount: displayedSummary.value.gross_change_amount,
            changePercentage: displayedSummary.value.gross_change_percentage,
        },
    ];
});

function updatePersonFilter(value: AcceptableValue): void {
    if (typeof value !== 'string') {
        return;
    }

    router.get(
        statisticsPaycheckGrowth.url({
            query: value === 'all' ? {} : { person: value },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
}

function xPosition(index: number, totalPoints: number): number {
    if (totalPoints <= 1) {
        return chartDimensions.paddingLeft + chartInnerWidth / 2;
    }

    return (
        chartDimensions.paddingLeft +
        (chartInnerWidth / (totalPoints - 1)) * index
    );
}

function yPosition(value: number): number {
    if (chartMaxValue.value === 0) {
        return chartDimensions.paddingTop + chartInnerHeight;
    }

    return (
        chartDimensions.paddingTop +
        chartInnerHeight -
        (value / chartMaxValue.value) * chartInnerHeight
    );
}

function buildPath(values: number[]): string {
    if (values.length === 0) {
        return '';
    }

    return values
        .map((value, index) => {
            const command = index === 0 ? 'M' : 'L';

            return `${command} ${xPosition(index, values.length)} ${yPosition(value)}`;
        })
        .join(' ');
}

function formatMoney(value: string | number | null): string {
    if (value === null) {
        return '—';
    }

    return `${formatSlovenianNumber(value)} €`;
}

function formatSignedMoney(value: string | number | null): string {
    if (value === null) {
        return '—';
    }

    const amount = Number(value);
    const prefix = amount > 0 ? '+' : '';

    return `${prefix}${formatSlovenianNumber(amount)} €`;
}

function formatSignedPercent(value: string | number | null): string {
    if (value === null) {
        return '—';
    }

    const amount = Number(value);
    const prefix = amount > 0 ? '+' : '';

    return `${prefix}${formatSlovenianNumber(amount)} %`;
}

function yearAxisLabel(row: PaycheckGrowthRow): string {
    return row.is_partial ? `${row.year}*` : String(row.year);
}

function partialYearsLabel(): string {
    const partialYears = props.rows
        .filter((row) => row.is_partial)
        .map((row) => row.year);

    if (partialYears.length === 0) {
        return '';
    }

    return `* delno leto: ${partialYears.join(', ')}`;
}

function summaryComparisonText(): string {
    if (displayedSummary.value.latest_year === null || displayedSummary.value.previous_year === null) {
        return 'Primerjava bo na voljo po dveh prikazanih letih.';
    }

    const latestRow = props.rows.at(-1);

    if (latestRow?.is_partial && latestRow.recorded_through_month !== null) {
        const monthLabel = new Intl.DateTimeFormat('sl-SI', {
            month: 'long',
        }).format(new Date(2026, latestRow.recorded_through_month - 1, 1));

        return `Primerjava ${displayedSummary.value.latest_year} glede na ${displayedSummary.value.previous_year} za obdobje do ${monthLabel}.`;
    }

    return `Primerjava ${displayedSummary.value.latest_year} glede na ${displayedSummary.value.previous_year}.`;
}
</script>

<template>
    <Head title="Rast plač" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <Heading
                title="Rast plač"
                description="Letni pregled neto, bruto in bonusov z grafom rasti po letih."
            />

            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <div class="grid gap-2">
                    <Label for="person-filter">Oseba</Label>
                    <Select
                        :model-value="props.selectedPerson"
                        @update:model-value="updatePersonFilter"
                    >
                        <SelectTrigger id="person-filter" class="w-[220px]">
                            <SelectValue placeholder="Izberi osebo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="filter in props.filters"
                                :key="filter.value"
                                :value="filter.value"
                            >
                                {{ filter.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div
                    class="flex items-center gap-3 rounded-lg border px-4 py-3 sm:mt-6"
                >
                    <Checkbox id="include-bonuses" v-model="includeBonuses" />
                    <Label for="include-bonuses" class="cursor-pointer">
                        Vključi bonuse
                    </Label>
                </div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <Card v-for="card in summaryCards" :key="card.label">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        {{ card.label }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(card.value) }}
                    </p>
                    <p class="mt-1 text-xs text-muted-foreground">
                        {{ summaryComparisonText() }}
                    </p>
                    <p
                        v-if="card.changeAmount !== null"
                        class="mt-2 text-sm font-medium"
                    >
                        {{ formatSignedMoney(card.changeAmount) }}
                        <span class="text-muted-foreground">
                            ({{ formatSignedPercent(card.changePercentage) }})
                        </span>
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Graf rasti po letih</CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="props.rows.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Ko bodo vnesene letne plače, se bo tukaj prikazal graf rasti.
                </div>

                <div v-else class="space-y-4">
                    <div class="flex flex-wrap gap-3 text-xs">
                        <div
                            v-for="series in chartLines"
                            :key="series.key"
                            class="flex items-center gap-2"
                        >
                            <span
                                class="h-2.5 w-2.5 rounded-full"
                                :style="{ backgroundColor: series.color }"
                            />
                            <span>{{ series.label }}</span>
                        </div>
                    </div>

                    <svg
                        :viewBox="`0 0 ${chartDimensions.width} ${chartDimensions.height}`"
                        class="w-full overflow-visible"
                        role="img"
                        aria-label="Graf rasti plač po letih"
                    >
                        <g>
                            <line
                                v-for="gridLine in chartGridLines"
                                :key="gridLine.y"
                                :x1="chartDimensions.paddingLeft"
                                :x2="
                                    chartDimensions.width -
                                    chartDimensions.paddingRight
                                "
                                :y1="gridLine.y"
                                :y2="gridLine.y"
                                stroke="currentColor"
                                class="text-border"
                                stroke-dasharray="4 6"
                            />
                            <text
                                v-for="gridLine in chartGridLines"
                                :key="`${gridLine.y}-label`"
                                :x="chartDimensions.paddingLeft - 8"
                                :y="gridLine.y + 4"
                                text-anchor="end"
                                class="fill-muted-foreground text-[11px]"
                            >
                                {{ gridLine.label }}
                            </text>
                        </g>

                        <g>
                            <path
                                v-for="series in chartLines"
                                :key="series.key"
                                :d="series.path"
                                fill="none"
                                :stroke="series.color"
                                stroke-width="3"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                            <template
                                v-for="series in chartLines"
                                :key="`${series.key}-points`"
                            >
                                <circle
                                    v-for="point in series.points"
                                    :key="`${series.key}-${point.x}-${point.y}`"
                                    :cx="point.x"
                                    :cy="point.y"
                                    :r="point.isPartial ? 5 : 4"
                                    :fill="series.color"
                                    :class="
                                        point.isPartial
                                            ? 'stroke-background stroke-[2]'
                                            : ''
                                    "
                                />
                            </template>
                        </g>

                        <g>
                            <text
                                v-for="(row, index) in props.rows"
                                :key="row.year"
                                :x="xPosition(index, props.rows.length)"
                                :y="chartDimensions.height - 12"
                                text-anchor="middle"
                                class="fill-muted-foreground text-[11px]"
                            >
                                {{ yearAxisLabel(row) }}
                            </text>
                        </g>
                    </svg>

                    <p class="text-xs text-muted-foreground">
                        {{ partialYearsLabel() }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Letni pregled</CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="props.rows.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Za izbran filter še ni vnesenih plač.
                </div>

                <Table v-else>
                    <thead>
                        <tr class="border-b">
                            <th
                                class="h-10 px-2 text-left font-medium whitespace-nowrap"
                            >
                                Leto
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Neto
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Bruto
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Bonusi bruto
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Bonusi neto
                            </th>
                            <th
                                class="h-10 px-2 text-left font-medium whitespace-nowrap"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in props.rows"
                            :key="row.year"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="p-2 font-medium align-middle whitespace-nowrap">
                                {{ row.year }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.net) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.gross) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.bonuses_gross) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.bonuses_net) }}
                            </td>
                            <td class="p-2 align-middle whitespace-nowrap">
                                <Badge
                                    :variant="
                                        row.is_partial ? 'outline' : 'secondary'
                                    "
                                >
                                    {{
                                        row.is_partial
                                            ? 'Delno leto'
                                            : 'Zaključeno leto'
                                    }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
