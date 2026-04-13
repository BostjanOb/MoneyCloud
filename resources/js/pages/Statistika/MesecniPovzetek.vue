<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import {
    store as snapshotStore,
    update as snapshotUpdate,
} from '@/actions/App/Http/Controllers/MonthlyPortfolioSnapshotController';
import {
    index as statisticsIndex,
    monthlySummary as statisticsMonthlySummary,
} from '@/actions/App/Http/Controllers/StatisticsController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table } from '@/components/ui/table';
import { sortMonthlyHistoryRows } from '@/lib/monthlySummary';
import { formatSlovenianNumber } from '@/lib/utils';

type MonthlyRow = {
    id: number;
    month_date: string;
    month_label: string;
    savings_amount: string;
    bond_amount: string;
    etf_amount: string;
    crypto_amount: string;
    stock_amount: string;
    total_amount: string;
    source: string;
    source_label: string;
    diff_amount: string | null;
    diff_percentage: string | null;
};

type MonthlyChartSeries = {
    key: string;
    label: string;
    color: string;
    values: number[];
};

type Props = {
    rows: MonthlyRow[];
    chartSeries: MonthlyChartSeries[];
    latest: MonthlyRow | null;
};

const props = defineProps<Props>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Statistika',
            href: statisticsIndex.url(),
        },
        {
            title: 'Mesečni povzetek',
            href: statisticsMonthlySummary.url(),
        },
    ],
});

const showSnapshotModal = ref(false);
const editingSnapshot = ref<MonthlyRow | null>(null);

const snapshotForm = useForm({
    month_date: currentMonthInput(),
    savings_amount: '0',
    bond_amount: '0',
    etf_amount: '0',
    crypto_amount: '0',
    stock_amount: '0',
});

const latestSnapshot = computed(
    () =>
        props.latest ?? {
            id: 0,
            month_date: '',
            month_label: 'Ni podatkov',
            savings_amount: '0.00',
            bond_amount: '0.00',
            etf_amount: '0.00',
            crypto_amount: '0.00',
            stock_amount: '0.00',
            total_amount: '0.00',
            source: 'manual',
            source_label: 'Ročno',
            diff_amount: null,
            diff_percentage: null,
        },
);

const latestCards = computed(() => [
    { label: 'Varčevanje', value: latestSnapshot.value.savings_amount },
    { label: 'Obveznice', value: latestSnapshot.value.bond_amount },
    { label: 'ETF', value: latestSnapshot.value.etf_amount },
    { label: 'Kripto', value: latestSnapshot.value.crypto_amount },
    { label: 'Delnice', value: latestSnapshot.value.stock_amount },
    { label: 'Skupaj', value: latestSnapshot.value.total_amount },
]);

const historyRows = computed(() => sortMonthlyHistoryRows(props.rows));

const chartDimensions = {
    width: 900,
    height: 320,
    paddingTop: 20,
    paddingRight: 20,
    paddingBottom: 40,
    paddingLeft: 48,
};

const chartMaxValue = computed(() =>
    Math.max(0, ...props.chartSeries.flatMap((series) => series.values)),
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
    props.chartSeries.map((series) => ({
        ...series,
        path: buildPath(series.values),
        points: series.values.map((value, index) => ({
            value,
            x: xPosition(index, series.values.length),
            y: yPosition(value),
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

function currentMonthInput(): string {
    return new Date().toISOString().slice(0, 7);
}

function toMonthInput(value: string): string {
    return value.slice(0, 7);
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

function formatMoney(value: string | number): string {
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

function valueTone(value: string | number | null): string {
    if (value === null) {
        return 'text-muted-foreground';
    }

    const amount = Number(value);

    if (amount > 0) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (amount < 0) {
        return 'text-destructive';
    }

    return 'text-foreground';
}

function resetSnapshotForm(): void {
    snapshotForm.defaults({
        month_date: currentMonthInput(),
        savings_amount: '0',
        bond_amount: '0',
        etf_amount: '0',
        crypto_amount: '0',
        stock_amount: '0',
    });
    snapshotForm.reset();
    snapshotForm.clearErrors();
}

function openCreateSnapshot(): void {
    editingSnapshot.value = null;
    resetSnapshotForm();
    showSnapshotModal.value = true;
}

function openEditSnapshot(row: MonthlyRow): void {
    editingSnapshot.value = row;
    snapshotForm.clearErrors();
    snapshotForm.month_date = toMonthInput(row.month_date);
    snapshotForm.savings_amount = row.savings_amount;
    snapshotForm.bond_amount = row.bond_amount;
    snapshotForm.etf_amount = row.etf_amount;
    snapshotForm.crypto_amount = row.crypto_amount;
    snapshotForm.stock_amount = row.stock_amount;
    showSnapshotModal.value = true;
}

function submitSnapshot(): void {
    snapshotForm.transform((data) => ({
        ...data,
        month_date: `${data.month_date}-01`,
    }));

    if (editingSnapshot.value !== null) {
        snapshotForm.put(snapshotUpdate.url(editingSnapshot.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showSnapshotModal.value = false;
                resetSnapshotForm();
            },
        });

        return;
    }

    snapshotForm.post(snapshotStore.url(), {
        preserveScroll: true,
        onSuccess: () => {
            showSnapshotModal.value = false;
            resetSnapshotForm();
        },
    });
}
</script>

<template>
    <Head title="Mesečni povzetek" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <Heading
                title="Mesečni povzetek"
                description="Mesečni povzetki portfelja z grafom in ročnim backfill vnosom."
            />
            <Button size="sm" @click="openCreateSnapshot()">
                Dodaj mesečni povzetek
            </Button>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="card in latestCards" :key="card.label">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        {{ card.label }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(card.value) }}
                    </p>
                    <p
                        v-if="props.latest !== null && card.label === 'Skupaj'"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        Zadnji shranjeni mesec: {{ props.latest.month_label }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Graf gibanja po mesecih</CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    v-if="props.rows.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Ko bo shranjen prvi mesečni povzetek, se bo tukaj prikazal
                    graf.
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
                        aria-label="Graf mesečnega povzetka"
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
                                    r="4"
                                    :fill="series.color"
                                />
                            </template>
                        </g>

                        <g>
                            <text
                                v-for="(row, index) in props.rows"
                                :key="row.id"
                                :x="xPosition(index, props.rows.length)"
                                :y="chartDimensions.height - 12"
                                text-anchor="middle"
                                class="fill-muted-foreground text-[11px]"
                            >
                                {{ row.month_label }}
                            </text>
                        </g>
                    </svg>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader
                class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
            >
                <CardTitle>Mesečna zgodovina</CardTitle>
                <p class="text-sm text-muted-foreground">
                    Ročni vnosi služijo za backfill obstoječih Excel podatkov.
                </p>
            </CardHeader>
            <CardContent>
                <div
                    v-if="props.rows.length === 0"
                    class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
                >
                    Še ni shranjenih mesečnih povzetkov.
                </div>

                <Table v-else>
                    <thead>
                        <tr class="border-b">
                            <th
                                class="h-10 px-2 text-left font-medium whitespace-nowrap"
                            >
                                Mesec
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Varčevanje
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Obveznice
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                ETF
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Kripto
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Delnice
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Skupaj
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                % Diff
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Diff
                            </th>
                            <th
                                class="h-10 px-2 text-left font-medium whitespace-nowrap"
                            >
                                Vir
                            </th>
                            <th
                                class="h-10 px-2 text-right font-medium whitespace-nowrap"
                            >
                                Akcije
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in historyRows"
                            :key="row.id"
                            class="border-b transition-colors hover:bg-muted/50"
                        >
                            <td class="p-2 align-middle whitespace-nowrap">
                                {{ row.month_label }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.savings_amount) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.bond_amount) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.etf_amount) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.crypto_amount) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                {{ formatMoney(row.stock_amount) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle font-semibold whitespace-nowrap"
                            >
                                {{ formatMoney(row.total_amount) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                                :class="valueTone(row.diff_percentage)"
                            >
                                {{ formatPercent(row.diff_percentage) }}
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                                :class="valueTone(row.diff_amount)"
                            >
                                {{ formatSignedMoney(row.diff_amount) }}
                            </td>
                            <td class="p-2 align-middle whitespace-nowrap">
                                <Badge
                                    :variant="
                                        row.source === 'scheduled'
                                            ? 'secondary'
                                            : 'outline'
                                    "
                                >
                                    {{ row.source_label }}
                                </Badge>
                            </td>
                            <td
                                class="p-2 text-right align-middle whitespace-nowrap"
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="openEditSnapshot(row)"
                                >
                                    Uredi
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </Table>
            </CardContent>
        </Card>
    </div>

    <Dialog :open="showSnapshotModal" @update:open="showSnapshotModal = $event">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        editingSnapshot === null
                            ? 'Dodaj mesečni povzetek'
                            : 'Uredi mesečni povzetek'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Vnesi zgodovinske vrednosti za izbrani mesec. Skupaj se
                    izračuna samodejno.
                </DialogDescription>
            </DialogHeader>

            <form
                class="grid gap-4 md:grid-cols-2"
                @submit.prevent="submitSnapshot"
            >
                <div class="grid gap-2 md:col-span-2">
                    <Label for="month_date">Mesec</Label>
                    <Input
                        id="month_date"
                        v-model="snapshotForm.month_date"
                        type="month"
                    />
                    <InputError :message="snapshotForm.errors.month_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="savings_amount">Varčevanje</Label>
                    <Input
                        id="savings_amount"
                        v-model="snapshotForm.savings_amount"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="snapshotForm.errors.savings_amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="bond_amount">Obveznice</Label>
                    <Input
                        id="bond_amount"
                        v-model="snapshotForm.bond_amount"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="snapshotForm.errors.bond_amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="etf_amount">ETF</Label>
                    <Input
                        id="etf_amount"
                        v-model="snapshotForm.etf_amount"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="snapshotForm.errors.etf_amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="crypto_amount">Kripto</Label>
                    <Input
                        id="crypto_amount"
                        v-model="snapshotForm.crypto_amount"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="snapshotForm.errors.crypto_amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="stock_amount">Delnice</Label>
                    <Input
                        id="stock_amount"
                        v-model="snapshotForm.stock_amount"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="snapshotForm.errors.stock_amount" />
                </div>

                <div
                    class="rounded-lg border border-dashed bg-muted/20 p-4 md:col-span-2"
                >
                    <p class="text-sm text-muted-foreground">
                        Predviden skupaj:
                        <span class="font-semibold text-foreground">
                            {{
                                formatMoney(
                                    Number(snapshotForm.savings_amount || 0) +
                                        Number(snapshotForm.bond_amount || 0) +
                                        Number(snapshotForm.etf_amount || 0) +
                                        Number(
                                            snapshotForm.crypto_amount || 0,
                                        ) +
                                        Number(snapshotForm.stock_amount || 0),
                                )
                            }}
                        </span>
                    </p>
                </div>

                <DialogFooter class="md:col-span-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showSnapshotModal = false"
                    >
                        Prekliči
                    </Button>
                    <Button type="submit" :disabled="snapshotForm.processing">
                        {{
                            editingSnapshot === null
                                ? 'Shrani mesec'
                                : 'Posodobi mesec'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
