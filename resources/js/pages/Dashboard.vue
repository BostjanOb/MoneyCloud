<script setup lang="ts">
import { VisAxis, VisLine, VisScatter, VisXYContainer } from '@unovis/vue';
import { Deferred, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartCrosshair,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    buildTrendChartData,
    type DashboardTrendChartPoint,
    type DashboardTrendPoint,
} from '@/lib/dashboard';
import {
    cn,
    formatEuro,
    formatSlovenianInteger,
    formatSlovenianPercent,
} from '@/lib/utils';
import { dashboard } from '@/routes';

type ChangeSegment = {
    key: string;
    label: string;
    amount: string;
    color: string;
    share_percentage: number;
};

type SnapshotChange = {
    available: boolean;
    snapshot_month_label: string | null;
    snapshot_total: string | null;
    current_total: string;
    diff_amount: string | null;
    diff_percentage: string | null;
    segments: ChangeSegment[];
};

type AllocationItem = {
    key: string;
    label: string;
    amount: string;
    share_percentage: number;
    month_diff_amount: string | null;
    color: string;
};

type IncomeMonth = {
    month_key: string;
    month_label: string;
    total_net: string;
    entered_people_count: number;
    expected_people_count: number;
    is_complete?: boolean;
};

type LongView = {
    available: boolean;
    months: number;
    growth_amount: string | null;
    growth_percentage: string | null;
    average_monthly_growth: string | null;
    consecutive_growth_months: number;
};

type TrendData = {
    latest_snapshot: {
        month_label: string;
        total_amount: string;
        diff_amount: string | null;
    } | null;
    points: DashboardTrendPoint[];
};

type InvestmentSummary = {
    total_invested: string;
    current_value: string;
    profit_loss: string;
    profit_loss_after_tax: string;
    purchase_count: number;
};

type TopPosition = {
    symbol: string;
    type_label: string;
    quantity: string;
    total_invested: string;
    current_value: string;
    profit_loss: string;
    profit_loss_after_tax: string;
};

type InvestmentsData = {
    summary: InvestmentSummary;
    top_positions: TopPosition[];
};

type Props = {
    netWorth: {
        current_total: string;
        as_of_label: string;
    };
    snapshotChange: SnapshotChange;
    allocation: AllocationItem[];
    income: {
        latest_full_month: IncomeMonth | null;
        current_month: IncomeMonth;
        monthly_interest: string;
    };
    longView: LongView;
    trend?: TrendData;
    investments?: InvestmentsData;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Pregled',
                href: dashboard(),
            },
        ],
    },
});

const eyebrowClass =
    'text-[11px] font-semibold tracking-[0.09em] text-muted-foreground uppercase';
const blockTitleClass = 'text-[15px] font-semibold tracking-tight';

const trendPeriod = ref<'12' | '24' | 'all'>('24');
const trendPoints = computed(() => props.trend?.points ?? []);
const visibleTrendPoints = computed<DashboardTrendPoint[]>(() => {
    if (trendPeriod.value === 'all') {
        return trendPoints.value;
    }

    const months = Number(trendPeriod.value);

    return trendPoints.value.slice(
        Math.max(0, trendPoints.value.length - months),
    );
});

const shortMonthFormatter = new Intl.DateTimeFormat('sl-SI', {
    month: 'short',
});
const longMonthFormatter = new Intl.DateTimeFormat('sl-SI', {
    month: 'long',
    year: 'numeric',
});

const trendChartConfig = {
    totalAmount: {
        label: 'Neto vrednost',
        color: '#10b981',
    },
} satisfies ChartConfig;

const trendChartData = computed(() =>
    buildTrendChartData(visibleTrendPoints.value),
);
const trendChartTicks = computed(() => {
    const step = Math.max(1, Math.ceil(trendChartData.value.length / 8));

    return trendChartData.value
        .filter((_, index) => index % step === 0)
        .map((point) => point.monthDate);
});

const longViewLabel = computed(() =>
    props.longView.months === 1
        ? 'Rast v 1 mesecu'
        : `Rast v ${props.longView.months} mesecih`,
);

function trendXAccessor(point: DashboardTrendChartPoint): Date {
    return point.monthDate;
}

function trendYAccessor(point: DashboardTrendChartPoint): number {
    return point.totalAmount;
}

function formatMoneyTick(value: number | Date): string {
    return formatMoney(Number(value));
}

function formatTrendMonthTick(value: number | Date): string {
    return shortMonthFormatter.format(new Date(value));
}

function formatTrendTooltipLabel(value: number | Date): string {
    return longMonthFormatter.format(new Date(value));
}

function formatTooltipMoney(value: unknown): string {
    return formatMoney(value as string | number | null);
}

function formatMoney(value: string | number | null): string {
    if (value === null) {
        return '—';
    }

    return formatEuro(value);
}

function formatSignedMoney(value: string | number | null): string {
    if (value === null) {
        return '—';
    }

    const amount = Number(value);

    return `${amount > 0 ? '+' : ''}${formatEuro(amount)}`;
}

function formatSignedPercent(value: string | number | null): string {
    if (value === null) {
        return '—';
    }

    const amount = Number(value);

    return `${amount > 0 ? '+' : ''}${formatSlovenianPercent(amount)}`;
}

function formatShare(value: number): string {
    return formatSlovenianPercent(value);
}

function formatQuantity(value: string | number): string {
    return new Intl.NumberFormat('sl-SI', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 8,
    }).format(Number(value));
}

function toneClass(value: string | number | null): string {
    if (value === null) {
        return 'text-foreground';
    }

    if (Number(value) < 0) {
        return 'text-destructive';
    }

    return 'text-emerald-600 dark:text-emerald-400';
}

function pillClass(value: string | number | null): string {
    return Number(value) < 0
        ? 'bg-destructive/10 text-destructive'
        : 'bg-emerald-600/10 text-emerald-600 dark:text-emerald-400';
}
</script>

<template>
    <Head title="Pregled" />

    <div class="flex flex-col gap-6 p-4">
        <section
            class="grid divide-y border-b pb-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)_minmax(0,0.85fr)] lg:divide-x lg:divide-y-0"
        >
            <div class="lg:pr-8">
                <p :class="eyebrowClass">Neto vrednost</p>
                <p
                    class="mt-3 text-4xl font-semibold tracking-tight tabular-nums sm:text-5xl"
                >
                    {{ formatMoney(props.netWorth.current_total) }}
                </p>
                <p class="mt-3 text-sm text-muted-foreground">
                    Živo stanje vseh kategorij ·
                    {{ props.netWorth.as_of_label }}
                </p>
            </div>

            <div class="pt-6 lg:px-8 lg:pt-0">
                <p :class="eyebrowClass">
                    {{
                        props.snapshotChange.available
                            ? `Sprememba od posnetka za ${props.snapshotChange.snapshot_month_label}`
                            : 'Sprememba od zadnjega posnetka'
                    }}
                </p>

                <template v-if="props.snapshotChange.available">
                    <div class="mt-3 flex flex-wrap items-baseline gap-2.5">
                        <span
                            :class="
                                cn(
                                    'text-3xl font-semibold tracking-tight tabular-nums',
                                    toneClass(props.snapshotChange.diff_amount),
                                )
                            "
                        >
                            {{
                                formatSignedMoney(
                                    props.snapshotChange.diff_amount,
                                )
                            }}
                        </span>
                        <span
                            v-if="props.snapshotChange.diff_percentage"
                            :class="
                                cn(
                                    'inline-flex h-6 items-center rounded-full px-2.5 text-xs font-semibold tabular-nums',
                                    pillClass(
                                        props.snapshotChange.diff_percentage,
                                    ),
                                )
                            "
                        >
                            {{
                                formatSignedPercent(
                                    props.snapshotChange.diff_percentage,
                                )
                            }}
                        </span>
                    </div>
                    <p class="mt-3 text-sm text-muted-foreground tabular-nums">
                        {{ formatMoney(props.snapshotChange.snapshot_total) }} →
                        {{ formatMoney(props.snapshotChange.current_total) }}
                    </p>
                </template>

                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Mesečni posnetek še ni dodan.
                </p>
            </div>

            <div class="pt-6 lg:pt-0 lg:pl-8">
                <p :class="eyebrowClass">Od kod je prišla sprememba</p>

                <template v-if="props.snapshotChange.available">
                    <div
                        class="mt-3 flex h-2.5 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            v-for="segment in props.snapshotChange.segments"
                            :key="`${segment.key}-bar`"
                            class="h-full"
                            :style="{
                                width: `${segment.share_percentage}%`,
                                backgroundColor: segment.color,
                            }"
                        />
                    </div>

                    <div class="mt-3 flex flex-col gap-2">
                        <div
                            v-for="segment in props.snapshotChange.segments"
                            :key="segment.key"
                            class="flex items-center gap-2.5 text-sm"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: segment.color }"
                            />
                            <span class="flex-1 text-muted-foreground">
                                {{ segment.label }}
                            </span>
                            <span class="font-medium tabular-nums">
                                {{ formatSignedMoney(segment.amount) }}
                            </span>
                        </div>
                    </div>
                </template>

                <p v-else class="mt-3 text-sm text-muted-foreground">
                    Razčlenitev se prikaže, ko bo dodan prvi mesečni posnetek.
                </p>
            </div>
        </section>

        <section class="my-4 flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <h3 :class="blockTitleClass">Gibanje neto vrednosti</h3>

                <Tabs v-if="trendPoints.length > 0" v-model="trendPeriod">
                    <TabsList>
                        <TabsTrigger value="12">12 M</TabsTrigger>
                        <TabsTrigger value="24">24 M</TabsTrigger>
                        <TabsTrigger value="all">Vse</TabsTrigger>
                    </TabsList>
                </Tabs>
            </div>

            <Deferred data="trend">
                <template #fallback>
                    <Skeleton class="h-[260px] w-full rounded-xl" />
                </template>

                <div
                    v-if="trendPoints.length === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Ko bodo dodani mesečni posnetki, se bo tukaj prikazalo
                    gibanje neto vrednosti.
                </div>

                <ChartContainer
                    v-else
                    :config="trendChartConfig"
                    cursor
                    class="!aspect-auto h-[240px] w-full sm:h-[260px]"
                >
                    <VisXYContainer
                        :data="trendChartData"
                        :y-domain="[0, undefined]"
                    >
                        <VisLine
                            :x="trendXAccessor"
                            :y="trendYAccessor"
                            color="var(--color-totalAmount)"
                            :line-width="3"
                        />
                        <VisScatter
                            :x="trendXAccessor"
                            :y="trendYAccessor"
                            color="var(--color-totalAmount)"
                            :size="6"
                        />
                        <VisAxis
                            type="x"
                            :x="trendXAccessor"
                            :tick-values="trendChartTicks"
                            :tick-format="formatTrendMonthTick"
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
                            :x="trendXAccessor"
                            :y="trendYAccessor"
                            color="var(--color-totalAmount)"
                            :template="
                                componentToString(
                                    trendChartConfig,
                                    ChartTooltipContent,
                                    {
                                        labelFormatter: formatTrendTooltipLabel,
                                        valueFormatter: formatTooltipMoney,
                                    },
                                )
                            "
                        />
                    </VisXYContainer>
                </ChartContainer>
            </Deferred>
        </section>

        <section
            class="my-4 grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
        >
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 :class="blockTitleClass">Sestava premoženja</h3>
                    <span class="text-sm text-muted-foreground">
                        Delež in mesečna sprememba po kategorijah
                    </span>
                </div>

                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead
                                class="px-0 text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Kategorija
                            </TableHead>
                            <TableHead
                                class="px-0 text-right text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Vrednost
                            </TableHead>
                            <TableHead
                                class="w-[210px] pr-0 pl-7 text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Delež
                            </TableHead>
                            <TableHead
                                class="px-0 text-right text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Ta mesec
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="item in props.allocation"
                            :key="item.key"
                        >
                            <TableCell class="px-0 py-3">
                                <span class="flex items-center gap-2.5">
                                    <span
                                        class="size-2.5 shrink-0 rounded-full"
                                        :style="{ backgroundColor: item.color }"
                                    />
                                    <span class="font-medium">
                                        {{ item.label }}
                                    </span>
                                </span>
                            </TableCell>
                            <TableCell
                                numeric
                                class="px-0 py-3 text-right font-medium"
                            >
                                {{ formatMoney(item.amount) }}
                            </TableCell>
                            <TableCell class="py-3 pr-0 pl-7">
                                <span class="flex items-center gap-3">
                                    <span
                                        class="h-1.5 w-[120px] overflow-hidden rounded-full bg-muted"
                                    >
                                        <span
                                            class="block h-full rounded-full"
                                            :style="{
                                                width: `${item.share_percentage}%`,
                                                backgroundColor: item.color,
                                            }"
                                        />
                                    </span>
                                    <span
                                        class="text-[13px] text-muted-foreground tabular-nums"
                                    >
                                        {{ formatShare(item.share_percentage) }}
                                    </span>
                                </span>
                            </TableCell>
                            <TableCell
                                numeric
                                :class="
                                    cn(
                                        'px-0 py-3 text-right',
                                        toneClass(item.month_diff_amount),
                                    )
                                "
                            >
                                {{ formatSignedMoney(item.month_diff_amount) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div class="flex flex-col gap-6">
                <Card class="gap-4 py-5 shadow-none">
                    <CardHeader class="px-5">
                        <CardTitle :class="blockTitleClass">Prihodki</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col px-5">
                        <div
                            class="flex items-baseline justify-between gap-3 py-2.5 text-sm"
                        >
                            <span class="text-muted-foreground">
                                Neto prejemki<template
                                    v-if="props.income.latest_full_month"
                                >
                                    ({{
                                        props.income.latest_full_month
                                            .month_label
                                    }})</template
                                >
                            </span>
                            <span class="font-medium tabular-nums">
                                {{
                                    formatMoney(
                                        props.income.latest_full_month
                                            ?.total_net ?? null,
                                    )
                                }}
                            </span>
                        </div>
                        <div
                            class="flex items-baseline justify-between gap-3 border-t py-2.5 text-sm"
                        >
                            <span class="text-muted-foreground">
                                Vnesene plače
                            </span>
                            <span class="font-medium">
                                {{
                                    props.income.current_month
                                        .entered_people_count
                                }}
                                od
                                {{
                                    props.income.current_month
                                        .expected_people_count
                                }}
                                oseb
                            </span>
                        </div>
                        <div
                            class="flex items-baseline justify-between gap-3 border-t py-2.5 text-sm"
                        >
                            <span class="text-muted-foreground">
                                Mesečne obresti
                            </span>
                            <span class="font-medium tabular-nums">
                                {{ formatMoney(props.income.monthly_interest) }}
                            </span>
                        </div>
                    </CardContent>
                </Card>

                <Card
                    v-if="props.longView.available"
                    class="gap-4 py-5 shadow-none"
                >
                    <CardHeader class="px-5">
                        <CardTitle :class="blockTitleClass">
                            Daljši pogled
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col px-5">
                        <div
                            class="flex items-baseline justify-between gap-3 py-2.5 text-sm"
                        >
                            <span class="text-muted-foreground">
                                {{ longViewLabel }}
                            </span>
                            <span class="flex items-baseline gap-2">
                                <span
                                    :class="
                                        cn(
                                            'font-medium tabular-nums',
                                            toneClass(
                                                props.longView.growth_amount,
                                            ),
                                        )
                                    "
                                >
                                    {{
                                        formatSignedMoney(
                                            props.longView.growth_amount,
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="props.longView.growth_percentage"
                                    class="text-[12.5px] text-muted-foreground tabular-nums"
                                >
                                    {{
                                        formatSignedPercent(
                                            props.longView.growth_percentage,
                                        )
                                    }}
                                </span>
                            </span>
                        </div>
                        <div
                            class="flex items-baseline justify-between gap-3 border-t py-2.5 text-sm"
                        >
                            <span class="text-muted-foreground">
                                Povprečna mesečna rast
                            </span>
                            <span class="font-medium tabular-nums">
                                {{
                                    formatMoney(
                                        props.longView.average_monthly_growth,
                                    )
                                }}
                            </span>
                        </div>
                        <div
                            class="flex items-baseline justify-between gap-3 border-t py-2.5 text-sm"
                        >
                            <span class="text-muted-foreground">
                                Zaporednih mesecev rasti
                            </span>
                            <span class="font-medium tabular-nums">
                                {{
                                    formatSlovenianInteger(
                                        props.longView
                                            .consecutive_growth_months,
                                    )
                                }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>

        <Deferred data="investments">
            <template #fallback>
                <section class="flex flex-col gap-4">
                    <Skeleton class="h-6 w-64" />
                    <Skeleton class="h-64 w-full rounded-xl" />
                </section>
            </template>

            <section class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center justify-between gap-6">
                    <h3 :class="blockTitleClass">
                        Naložbe in največje pozicije
                    </h3>

                    <div
                        v-if="props.investments?.summary.purchase_count !== 0"
                        class="flex flex-wrap gap-x-8 gap-y-3"
                    >
                        <div class="flex flex-col gap-0.5">
                            <span :class="eyebrowClass">Vloženo</span>
                            <span class="text-base font-semibold tabular-nums">
                                {{
                                    formatMoney(
                                        props.investments?.summary
                                            .total_invested ?? null,
                                    )
                                }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span :class="eyebrowClass">Vrednost</span>
                            <span class="text-base font-semibold tabular-nums">
                                {{
                                    formatMoney(
                                        props.investments?.summary
                                            .current_value ?? null,
                                    )
                                }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span :class="eyebrowClass">Dobiček</span>
                            <span
                                :class="
                                    cn(
                                        'text-base font-semibold tabular-nums',
                                        toneClass(
                                            props.investments?.summary
                                                .profit_loss ?? null,
                                        ),
                                    )
                                "
                            >
                                {{
                                    formatSignedMoney(
                                        props.investments?.summary
                                            .profit_loss ?? null,
                                    )
                                }}
                            </span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            <span :class="eyebrowClass">Po davku</span>
                            <span
                                :class="
                                    cn(
                                        'text-base font-semibold tabular-nums',
                                        toneClass(
                                            props.investments?.summary
                                                .profit_loss_after_tax ?? null,
                                        ),
                                    )
                                "
                            >
                                {{
                                    formatSignedMoney(
                                        props.investments?.summary
                                            .profit_loss_after_tax ?? null,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <div
                    v-if="props.investments?.summary.purchase_count === 0"
                    class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Ko bodo dodani nakupi, se bo tukaj prikazal pregled vložkov
                    in največjih pozicij.
                </div>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead
                                class="px-0 text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Pozicija
                            </TableHead>
                            <TableHead
                                class="px-0 pl-7 text-right text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Količina
                            </TableHead>
                            <TableHead
                                class="px-0 pl-7 text-right text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Vloženo
                            </TableHead>
                            <TableHead
                                class="px-0 pl-7 text-right text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Vrednost
                            </TableHead>
                            <TableHead
                                class="px-0 pl-7 text-right text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Dobiček
                            </TableHead>
                            <TableHead
                                class="px-0 pl-7 text-right text-xs tracking-wide text-muted-foreground uppercase"
                            >
                                Po davku
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="position in props.investments
                                ?.top_positions ?? []"
                            :key="position.symbol"
                        >
                            <TableCell class="px-0 py-3">
                                <span class="font-medium">
                                    {{ position.symbol }}
                                </span>
                                <span
                                    class="ml-2 text-xs text-muted-foreground"
                                >
                                    {{ position.type_label }}
                                </span>
                            </TableCell>
                            <TableCell
                                numeric
                                class="px-0 py-3 pl-7 text-right"
                            >
                                {{ formatQuantity(position.quantity) }}
                            </TableCell>
                            <TableCell
                                numeric
                                class="px-0 py-3 pl-7 text-right"
                            >
                                {{ formatMoney(position.total_invested) }}
                            </TableCell>
                            <TableCell
                                numeric
                                class="px-0 py-3 pl-7 text-right font-medium"
                            >
                                {{ formatMoney(position.current_value) }}
                            </TableCell>
                            <TableCell
                                numeric
                                :class="
                                    cn(
                                        'px-0 py-3 pl-7 text-right',
                                        toneClass(position.profit_loss),
                                    )
                                "
                            >
                                {{ formatSignedMoney(position.profit_loss) }}
                            </TableCell>
                            <TableCell
                                numeric
                                :class="
                                    cn(
                                        'px-0 py-3 pl-7 text-right',
                                        toneClass(
                                            position.profit_loss_after_tax,
                                        ),
                                    )
                                "
                            >
                                {{
                                    formatSignedMoney(
                                        position.profit_loss_after_tax,
                                    )
                                }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </section>
        </Deferred>
    </div>
</template>
