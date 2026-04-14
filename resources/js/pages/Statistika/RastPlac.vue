<script setup lang="ts">
import { VisAxis, VisLine, VisScatter, VisXYContainer } from '@unovis/vue';
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import {
    index as statisticsIndex,
    paycheckGrowth as statisticsPaycheckGrowth,
} from '@/actions/App/Http/Controllers/StatisticsController';
import Heading from '@/components/Heading.vue';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartCrosshair,
    ChartLegendContent,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';
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
    buildPaycheckGrowthChartData,
    buildPaycheckGrowthSummary,
    displayedPaycheckGrowthRows,
    displayedPaycheckGrowthSeries,
    type PaycheckGrowthChartPoint,
} from '@/lib/paycheckGrowth';
import type {
    PaycheckGrowthRow,
    PaycheckGrowthSeries,
    PaycheckGrowthSummary,
} from '@/lib/paycheckGrowth';
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
const yearNumberFormatter = new Intl.NumberFormat('sl-SI', {
    maximumFractionDigits: 0,
});

const visibleSeries = computed(() =>
    displayedPaycheckGrowthSeries(props.chartSeries, includeBonuses.value),
);

const displayedSummary = computed(() =>
    buildPaycheckGrowthSummary(props.rows, includeBonuses.value),
);
const displayedRows = computed(() => displayedPaycheckGrowthRows(props.rows));
const visibleChartData = computed(() =>
    buildPaycheckGrowthChartData(props.rows, visibleSeries.value),
);
const visibleChartConfig = computed<ChartConfig>(() =>
    Object.fromEntries(
        visibleSeries.value.map((series) => [
            series.key,
            {
                label: series.label,
                color: series.color,
            },
        ]),
    ),
);
const visibleChartTicks = computed(() =>
    visibleChartData.value.map((point) => point.year),
);
const visibleChartYAccessors = computed(() =>
    visibleSeries.value.map(
        (series) =>
            (point: PaycheckGrowthChartPoint): number =>
                Number(point[series.key] ?? 0),
    ),
);
const visibleChartColors = computed(() =>
    visibleSeries.value.map((series) => `var(--color-${series.key})`),
);

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
            label: includeBonuses.value
                ? 'Neto dohodek z bonusi'
                : 'Neto dohodek',
            value: netValue,
            changeAmount: displayedSummary.value.net_change_amount,
            changePercentage: displayedSummary.value.net_change_percentage,
        },
        {
            label: includeBonuses.value
                ? 'Bruto dohodek z bonusi'
                : 'Bruto dohodek',
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

function paycheckGrowthXAccessor(point: PaycheckGrowthChartPoint): number {
    return point.year;
}

function paycheckGrowthYAccessor(
    key: string,
): (point: PaycheckGrowthChartPoint) => number {
    return (point) => Number(point[key] ?? 0);
}

function formatPaycheckYearTick(value: number | Date): string {
    const year = Number(value);
    const point = visibleChartData.value.find((item) => item.year === year);

    return point?.yearLabel ?? yearNumberFormatter.format(year);
}

function formatPaycheckTooltipLabel(value: number | Date): string {
    const year = Number(value);
    const point = visibleChartData.value.find((item) => item.year === year);

    if (!point) {
        return yearNumberFormatter.format(year);
    }

    return point.isPartial
        ? `${point.yearLabel} (delno leto)`
        : point.yearLabel;
}

function formatMoneyTick(value: number | Date): string {
    return formatMoney(Number(value));
}

function formatTooltipMoney(value: unknown): string {
    return formatMoney(value as string | number | null);
}

function paycheckPointSize(point: PaycheckGrowthChartPoint): number {
    return point.isPartial ? 10 : 7;
}

function paycheckPointStrokeWidth(point: PaycheckGrowthChartPoint): number {
    return point.isPartial ? 2 : 0;
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
    if (
        displayedSummary.value.latest_year === null ||
        displayedSummary.value.previous_year === null
    ) {
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
                    Ko bodo vnesene letne plače, se bo tukaj prikazal graf
                    rasti.
                </div>

                <div v-else class="space-y-4">
                    <ChartContainer
                        :config="visibleChartConfig"
                        cursor
                        class="!aspect-auto h-[220px] w-full sm:h-[240px]"
                    >
                        <VisXYContainer
                            :data="visibleChartData"
                            :y-domain="[0, undefined]"
                        >
                            <VisLine
                                v-for="series in visibleSeries"
                                :key="`${series.key}-line`"
                                :x="paycheckGrowthXAccessor"
                                :y="paycheckGrowthYAccessor(series.key)"
                                :color="`var(--color-${series.key})`"
                                :line-width="3"
                            />
                            <VisScatter
                                v-for="series in visibleSeries"
                                :key="`${series.key}-points`"
                                :x="paycheckGrowthXAccessor"
                                :y="paycheckGrowthYAccessor(series.key)"
                                :color="`var(--color-${series.key})`"
                                :size="paycheckPointSize"
                                stroke-color="var(--background)"
                                :stroke-width="paycheckPointStrokeWidth"
                            />
                            <VisAxis
                                type="x"
                                :x="paycheckGrowthXAccessor"
                                :tick-values="visibleChartTicks"
                                :tick-format="formatPaycheckYearTick"
                                :tick-line="false"
                                :domain-line="false"
                                :grid-line="false"
                            />
                            <VisAxis
                                type="y"
                                :tick-format="formatMoneyTick"
                                :tick-line="false"
                                :domain-line="false"
                                :grid-line="true"
                            />
                            <ChartTooltip />
                            <ChartCrosshair
                                :x="paycheckGrowthXAccessor"
                                :y="visibleChartYAccessors"
                                :color="visibleChartColors"
                                :template="
                                    componentToString(
                                        visibleChartConfig,
                                        ChartTooltipContent,
                                        {
                                            labelFormatter:
                                                formatPaycheckTooltipLabel,
                                            valueFormatter: formatTooltipMoney,
                                        },
                                    )
                                "
                            />
                        </VisXYContainer>
                        <ChartLegendContent
                            vertical-align="top"
                            class="flex-wrap justify-start"
                        />
                    </ChartContainer>

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
                                Bonusi neto
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Neto seštevek
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
                            v-for="row in displayedRows"
                            :key="row.year"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td
                                class="p-2 align-middle font-medium whitespace-nowrap"
                            >
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
                                {{ formatMoney(row.bonuses_net) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.net_with_bonuses) }}
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
