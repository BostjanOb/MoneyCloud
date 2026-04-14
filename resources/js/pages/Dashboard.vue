<script setup lang="ts">
import { VisAxis, VisLine, VisScatter, VisXYContainer } from '@unovis/vue';
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import type { ChartConfig } from '@/components/ui/chart';
import {
    ChartContainer,
    ChartCrosshair,
    ChartTooltip,
    ChartTooltipContent,
    componentToString,
} from '@/components/ui/chart';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import {
    buildTrendChartData,
    type DashboardTrendChartPoint,
    type DashboardTrendPoint,
} from '@/lib/dashboard';
import { cn, formatSlovenianNumber } from '@/lib/utils';
import { dashboard } from '@/routes';

type HeroMetric = {
    title: string;
    value: string | null;
    subtitle: string;
    tone: 'positive' | 'negative' | 'neutral' | 'warning';
    percentage?: string | null;
};

type AllocationItem = {
    key: string;
    label: string;
    amount: string;
    share_percentage: number;
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

type AlertItem = {
    key: string;
    title: string;
    message: string;
    href: string;
    action_label: string;
};

type QuickAction = {
    label: string;
    href: string;
    variant: 'default' | 'outline';
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
    hero: {
        current_total: HeroMetric;
        snapshot_change: HeroMetric;
        latest_income: HeroMetric;
        monthly_interest: HeroMetric;
    };
    allocation: {
        total_amount: string;
        items: AllocationItem[];
    };
    income: {
        latest_full_month: IncomeMonth | null;
        current_month: IncomeMonth;
    };
    alerts: AlertItem[];
    quickActions: QuickAction[];
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

const heroCards = computed(() => [
    { key: 'current_total', ...props.hero.current_total },
    { key: 'snapshot_change', ...props.hero.snapshot_change },
    { key: 'latest_income', ...props.hero.latest_income },
    { key: 'monthly_interest', ...props.hero.monthly_interest },
]);

const trendPoints = computed(() => props.trend?.points ?? []);
const shortMonthFormatter = new Intl.DateTimeFormat('sl-SI', {
    month: 'short',
});
const longMonthFormatter = new Intl.DateTimeFormat('sl-SI', {
    month: 'long',
    year: 'numeric',
});

const trendChartConfig = {
    totalAmount: {
        label: 'Skupna vrednost',
        color: '#10b981',
    },
} satisfies ChartConfig;

const trendChartData = computed(() => buildTrendChartData(trendPoints.value));
const trendChartTicks = computed(() => {
    const step = Math.max(1, Math.ceil(trendChartData.value.length / 6));

    return trendChartData.value
        .filter((_, index) => index % step === 0)
        .map((point) => point.monthDate);
});

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

function formatPercent(value: string | number | null): string {
    if (value === null) {
        return '—';
    }

    const amount = Number(value);
    const prefix = amount > 0 ? '+' : '';

    return `${prefix}${formatSlovenianNumber(amount)} %`;
}

function formatQuantity(value: string | number): string {
    return new Intl.NumberFormat('sl-SI', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 8,
    }).format(Number(value));
}

function heroToneClass(tone: HeroMetric['tone']): string {
    if (tone === 'positive') {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (tone === 'negative') {
        return 'text-destructive';
    }

    if (tone === 'warning') {
        return 'text-amber-600 dark:text-amber-400';
    }

    return 'text-foreground';
}

function heroCardClass(key: string): string {
    return key === 'current_total'
        ? 'border-emerald-200/70 bg-gradient-to-br from-emerald-500/8 via-transparent to-sky-500/10 dark:border-emerald-900'
        : '';
}
</script>

<template>
    <Head title="Pregled" />

    <div class="flex flex-col gap-6 p-4">
        <Heading
            title="Pregled"
            description="Koliko imaš zdaj, kako se stanje premika in kaj se dogaja pri prihodkih."
        />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <Card
                v-for="card in heroCards"
                :key="card.key"
                :class="heroCardClass(card.key)"
            >
                <CardHeader class="gap-2 pb-3">
                    <CardTitle
                        class="text-sm font-medium text-muted-foreground"
                    >
                        {{ card.title }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2">
                    <div class="flex items-start justify-between gap-3">
                        <p
                            :class="
                                cn(
                                    'text-2xl font-semibold tracking-tight',
                                    heroToneClass(card.tone),
                                )
                            "
                        >
                            {{
                                card.key === 'snapshot_change'
                                    ? formatSignedMoney(card.value)
                                    : formatMoney(card.value)
                            }}
                        </p>
                        <span
                            v-if="card.percentage"
                            class="rounded-full bg-muted px-2 py-1 text-xs font-medium text-muted-foreground"
                        >
                            {{ formatPercent(card.percentage) }}
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ card.subtitle }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <section
            class="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_minmax(320px,1fr)]"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Gibanje skupne vrednosti</CardTitle>
                </CardHeader>
                <CardContent>
                    <Deferred data="trend">
                        <template #fallback>
                            <div class="space-y-4">
                                <Skeleton class="h-5 w-56" />
                                <Skeleton class="h-72 w-full rounded-xl" />
                                <div class="grid gap-3 sm:grid-cols-3">
                                    <Skeleton class="h-20 w-full" />
                                    <Skeleton class="h-20 w-full" />
                                    <Skeleton class="h-20 w-full" />
                                </div>
                            </div>
                        </template>

                        <div
                            v-if="trendPoints.length === 0"
                            class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                        >
                            Ko bodo dodani mesečni posnetki, se bo tukaj
                            prikazalo gibanje skupne vrednosti.
                        </div>

                        <div v-else class="space-y-5">
                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >
                                <div class="space-y-1">
                                    <p class="text-sm text-muted-foreground">
                                        {{ trendPoints.length }} zabeleženih
                                        mesecev
                                    </p>
                                    <p class="text-lg font-semibold">
                                        {{
                                            formatMoney(
                                                props.trend?.latest_snapshot
                                                    ?.total_amount ?? null,
                                            )
                                        }}
                                    </p>
                                </div>

                                <div
                                    v-if="props.trend?.latest_snapshot"
                                    class="rounded-lg bg-muted/50 px-3 py-2 text-right"
                                >
                                    <p
                                        class="text-xs tracking-wide text-muted-foreground uppercase"
                                    >
                                        Zadnji posnetek
                                    </p>
                                    <p class="font-medium">
                                        {{
                                            props.trend.latest_snapshot
                                                .month_label
                                        }}
                                    </p>
                                </div>
                            </div>

                            <ChartContainer
                                :config="trendChartConfig"
                                cursor
                                class="!aspect-auto h-[220px] w-full sm:h-[240px]"
                            >
                                <VisXYContainer
                                    :data="trendChartData"
                                    :y-domain="[0, undefined]"
                                >
                                    <VisLine
                                        :x="trendXAccessor"
                                        :y="trendYAccessor"
                                        color="var(--color-totalAmount)"
                                        :line-width="4"
                                    />
                                    <VisScatter
                                        :x="trendXAccessor"
                                        :y="trendYAccessor"
                                        color="var(--color-totalAmount)"
                                        :size="8"
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
                                                    labelFormatter:
                                                        formatTrendTooltipLabel,
                                                    valueFormatter:
                                                        formatTooltipMoney,
                                                },
                                            )
                                        "
                                    />
                                </VisXYContainer>
                            </ChartContainer>

                            <div class="grid gap-3 sm:grid-cols-3">
                                <div
                                    v-for="point in trendPoints.slice(-3)"
                                    :key="point.month_date"
                                    class="rounded-xl border bg-muted/20 p-4"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        {{ point.month_label }}
                                    </p>
                                    <p class="mt-1 text-lg font-semibold">
                                        {{ formatMoney(point.total_amount) }}
                                    </p>
                                    <p
                                        class="mt-1 text-sm"
                                        :class="
                                            heroToneClass(
                                                Number(
                                                    point.diff_amount ?? 0,
                                                ) >= 0
                                                    ? 'positive'
                                                    : 'negative',
                                            )
                                        "
                                    >
                                        {{
                                            formatSignedMoney(point.diff_amount)
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </Deferred>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Razporeditev portfelja</CardTitle>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="space-y-1">
                        <p class="text-sm text-muted-foreground">
                            Skupna vrednost
                        </p>
                        <p class="text-2xl font-semibold">
                            {{ formatMoney(props.allocation.total_amount) }}
                        </p>
                    </div>

                    <div class="flex h-3 overflow-hidden rounded-full bg-muted">
                        <div
                            v-for="item in props.allocation.items"
                            :key="`${item.key}-bar`"
                            class="h-full"
                            :style="{
                                width: `${item.share_percentage}%`,
                                backgroundColor: item.color,
                            }"
                        />
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="item in props.allocation.items"
                            :key="item.key"
                            class="space-y-2"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full"
                                        :style="{ backgroundColor: item.color }"
                                    />
                                    <span class="text-sm font-medium">
                                        {{ item.label }}
                                    </span>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium">
                                        {{ formatMoney(item.amount) }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            formatPercent(item.share_percentage)
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :style="{
                                        width: `${item.share_percentage}%`,
                                        backgroundColor: item.color,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </section>

        <Deferred data="investments">
            <template #fallback>
                <Card>
                    <CardHeader>
                        <CardTitle>Vložki in pozicije</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <Skeleton class="h-24 w-full" />
                            <Skeleton class="h-24 w-full" />
                            <Skeleton class="h-24 w-full" />
                            <Skeleton class="h-24 w-full" />
                        </div>
                        <Skeleton class="h-64 w-full rounded-xl" />
                    </CardContent>
                </Card>
            </template>

            <Card>
                <CardHeader>
                    <CardTitle>Vložki in pozicije</CardTitle>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div
                        v-if="props.investments?.summary.purchase_count === 0"
                        class="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground"
                    >
                        Ko bodo dodani nakupi, se bo tukaj prikazal pregled
                        vložkov in top pozicij.
                    </div>

                    <template v-else>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            <Card>
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
                                                props.investments?.summary
                                                    .total_invested ?? null,
                                            )
                                        }}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader class="pb-2">
                                    <CardTitle
                                        class="text-sm text-muted-foreground"
                                    >
                                        Trenutna vrednost
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p class="text-2xl font-semibold">
                                        {{
                                            formatMoney(
                                                props.investments?.summary
                                                    .current_value ?? null,
                                            )
                                        }}
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader class="pb-2">
                                    <CardTitle
                                        class="text-sm text-muted-foreground"
                                    >
                                        Dobiček / izguba
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p
                                        :class="
                                            cn(
                                                'text-2xl font-semibold',
                                                heroToneClass(
                                                    Number(
                                                        props.investments
                                                            ?.summary
                                                            .profit_loss ?? 0,
                                                    ) >= 0
                                                        ? 'positive'
                                                        : 'negative',
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
                                    </p>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader class="pb-2">
                                    <CardTitle
                                        class="text-sm text-muted-foreground"
                                    >
                                        Dobiček po davku
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p
                                        :class="
                                            cn(
                                                'text-2xl font-semibold',
                                                heroToneClass(
                                                    Number(
                                                        props.investments
                                                            ?.summary
                                                            .profit_loss_after_tax ??
                                                            0,
                                                    ) >= 0
                                                        ? 'positive'
                                                        : 'negative',
                                                ),
                                            )
                                        "
                                    >
                                        {{
                                            formatSignedMoney(
                                                props.investments?.summary
                                                    .profit_loss_after_tax ??
                                                    null,
                                            )
                                        }}
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        <div class="overflow-x-auto rounded-xl border">
                            <table class="min-w-full text-sm">
                                <thead
                                    class="bg-muted/40 text-left text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-4 py-3 font-medium">
                                            Pozicija
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right font-medium"
                                        >
                                            Količina
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right font-medium"
                                        >
                                            Vloženo
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right font-medium"
                                        >
                                            Trenutna vrednost
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right font-medium"
                                        >
                                            Dobiček / izguba
                                        </th>
                                        <th
                                            class="px-4 py-3 text-right font-medium"
                                        >
                                            Po davku
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="position in props.investments
                                            ?.top_positions ?? []"
                                        :key="position.symbol"
                                        class="border-t"
                                    >
                                        <td class="px-4 py-3">
                                            <div class="font-medium">
                                                {{ position.symbol }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ position.type_label }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            {{
                                                formatQuantity(
                                                    position.quantity,
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            {{
                                                formatMoney(
                                                    position.total_invested,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right font-medium"
                                        >
                                            {{
                                                formatMoney(
                                                    position.current_value,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right"
                                            :class="
                                                heroToneClass(
                                                    Number(
                                                        position.profit_loss,
                                                    ) >= 0
                                                        ? 'positive'
                                                        : 'negative',
                                                )
                                            "
                                        >
                                            {{
                                                formatSignedMoney(
                                                    position.profit_loss,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 text-right"
                                            :class="
                                                heroToneClass(
                                                    Number(
                                                        position.profit_loss_after_tax,
                                                    ) >= 0
                                                        ? 'positive'
                                                        : 'negative',
                                                )
                                            "
                                        >
                                            {{
                                                formatSignedMoney(
                                                    position.profit_loss_after_tax,
                                                )
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </Deferred>

        <section
            class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(280px,0.8fr)]"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Opozorila</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="props.alerts.length === 0"
                        class="rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
                    >
                        Trenutno ni posebnih opozoril. Podatki za pregled so
                        usklajeni.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="alert in props.alerts"
                            :key="alert.key"
                            class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 dark:border-amber-900 dark:bg-amber-950/20"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="space-y-1">
                                    <p
                                        class="font-medium text-amber-950 dark:text-amber-100"
                                    >
                                        {{ alert.title }}
                                    </p>
                                    <p
                                        class="text-sm text-amber-900/80 dark:text-amber-100/80"
                                    >
                                        {{ alert.message }}
                                    </p>
                                </div>

                                <Button
                                    as-child
                                    variant="outline"
                                    size="sm"
                                    class="border-amber-300 bg-transparent"
                                >
                                    <Link :href="alert.href">
                                        {{ alert.action_label }}
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Hitre akcije</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-3">
                    <Button
                        v-for="action in props.quickActions"
                        :key="action.label"
                        as-child
                        :variant="action.variant"
                        class="justify-start"
                    >
                        <Link :href="action.href">
                            {{ action.label }}
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </section>
    </div>
</template>
