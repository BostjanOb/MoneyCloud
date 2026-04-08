<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref, watch } from 'vue';
import { store as bonusStore, update as bonusUpdate, destroy as bonusDestroy } from '@/actions/App/Http/Controllers/BonusController';
import { index as placeIndex, store as paycheckStore, update as paycheckUpdate, destroy as paycheckDestroy } from '@/actions/App/Http/Controllers/PaycheckController';
import { store as yearStore, update as yearUpdate } from '@/actions/App/Http/Controllers/PaycheckYearController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatSlovenianNumber } from '@/lib/utils';

type Paycheck = {
    id: number;
    paycheck_year_id: number;
    month: number;
    net: string | null;
    gross: string;
    contributions: string;
    taxes: string;
};

type Bonus = {
    id: number;
    paycheck_year_id: number;
    type: string;
    amount: string;
    taxable: boolean;
    paid_tax: string;
    description: string | null;
    paid_at: string | null;
};

type BonusTypeOption = {
    value: string;
    label: string;
};

type PaycheckYear = {
    id: number;
    employee: string;
    year: number;
    child1_months: number;
    child2_months: number;
    child3_months: number;
};

type Calculation = {
    sum_gross: string;
    sum_net: string;
    sum_contributions: string;
    sum_taxes: string;
    osnova: string;
    olajsave: string;
    davcna_osnova: string;
    dohodnina: string;
    razlika: string;
    has_tax_settings: boolean;
    breakdown: {
        general_relief: string;
        child_relief1: string;
        child_relief2: string;
        child_relief3: string;
    };
    projection: {
        months_used: number;
        is_final: boolean;
        sum_gross: string;
        sum_net: string;
        sum_contributions: string;
        sum_taxes: string;
        osnova: string;
        olajsave: string;
        davcna_osnova: string;
        dohodnina: string;
        razlika: string;
    };
};

type Props = {
    employee: string;
    employeeLabel: string;
    year: number;
    paycheckYear: PaycheckYear | null;
    paychecks: Paycheck[];
    bonuses: Bonus[];
    bonusTypeOptions: BonusTypeOption[];
    calculation: Calculation | null;
    availableYears: number[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Plače',
                href: placeIndex.url('bostjan'),
            },
        ],
    },
});

const monthNames = [
    'Januar', 'Februar', 'Marec', 'April', 'Maj', 'Junij',
    'Julij', 'Avgust', 'September', 'Oktober', 'November', 'December',
];

function formatNumber(value: string | number): string {
    return formatSlovenianNumber(value);
}

function formatOptionalNumber(value: string | number | null): string {
    if (value === null || value === '') {
        return '–';
    }

    return formatSlovenianNumber(value);
}

function projectionSummary(calculation: Calculation): string {
    if (calculation.projection.months_used === 0) {
        return 'Ocena bo na voljo po prvi vneseni plači.';
    }

    if (calculation.projection.is_final) {
        return 'Končni obračun temelji na vseh 12 vnesenih plačah.';
    }

    if (calculation.projection.months_used === 3) {
        return 'Ocena temelji na povprečju zadnjih 3 plač, preračunanem na 12 mesecev.';
    }

    return `Ocena temelji na povprečju zadnjih ${calculation.projection.months_used} plač, preračunanem na 12 mesecev.`;
}

function settlementLabel(value: string): string {
    return Number(value) > 0 ? 'Predvideno doplačilo' : 'Predvideno vračilo';
}

function settlementTitle(calculation: Calculation): string {
    if (calculation.projection.is_final) {
        return Number(calculation.projection.razlika) > 0 ? 'Doplačilo ob koncu leta' : 'Vračilo ob koncu leta';
    }

    return settlementLabel(calculation.projection.razlika);
}

const bonusTypeLabels = computed(() => {
    return new Map(props.bonusTypeOptions.map((option) => [option.value, option.label]));
});

const hasTaxableBonuses = computed(() => props.bonuses.some((bonus) => bonus.taxable));

const defaultBonusType = props.bonusTypeOptions[0]?.value ?? 'regres';

function blankBonusFormData() {
    return {
        paycheck_year_id: 0,
        type: defaultBonusType,
        amount: '',
        taxable: false,
        paid_tax: '',
        description: '',
        paid_at: '',
    };
}

function bonusTypeLabel(type: string): string {
    return bonusTypeLabels.value.get(type) ?? type;
}

// Paycheck modal state
const showPaycheckModal = ref(false);
const editingPaycheck = ref<Paycheck | null>(null);

const paycheckForm = useForm({
    paycheck_year_id: 0,
    month: '',
    net: '',
    gross: '',
    contributions: '',
    taxes: '',
});

const usedMonths = computed(() => props.paychecks.map((p) => p.month));
const availableMonths = computed(() =>
    Array.from({ length: 12 }, (_, i) => i + 1).filter((m) => !usedMonths.value.includes(m)),
);

function openAddPaycheck() {
    if (!props.paycheckYear) {
return;
}

    editingPaycheck.value = null;
    paycheckForm.reset();
    paycheckForm.paycheck_year_id = props.paycheckYear.id;
    paycheckForm.month = availableMonths.value.length > 0 ? String(availableMonths.value[0]) : '';
    showPaycheckModal.value = true;
}

function openEditPaycheck(paycheck: Paycheck) {
    editingPaycheck.value = paycheck;
    paycheckForm.paycheck_year_id = paycheck.paycheck_year_id;
    paycheckForm.month = String(paycheck.month);
    paycheckForm.net = paycheck.net ?? '';
    paycheckForm.gross = paycheck.gross;
    paycheckForm.contributions = paycheck.contributions;
    paycheckForm.taxes = paycheck.taxes;
    showPaycheckModal.value = true;
}

function submitPaycheck() {
    if (editingPaycheck.value) {
        paycheckForm.put(paycheckUpdate.url(editingPaycheck.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showPaycheckModal.value = false;
            },
        });
    } else {
        paycheckForm.post(paycheckStore.url(), {
            preserveScroll: true,
            onSuccess: () => {
                showPaycheckModal.value = false;
            },
        });
    }
}

function deletePaycheck(paycheck: Paycheck) {
    if (!confirm('Ste prepričani, da želite izbrisati to plačo?')) {
return;
}

    router.delete(paycheckDestroy.url(paycheck.id), { preserveScroll: true });
}

// Bonus modal state
const showBonusModal = ref(false);
const editingBonus = ref<Bonus | null>(null);
const bonusForm = useForm(blankBonusFormData());

function resetBonusForm() {
    bonusForm.defaults(blankBonusFormData());
    bonusForm.reset();
    bonusForm.clearErrors();
}

function openAddBonus() {
    if (!props.paycheckYear) {
return;
}

    editingBonus.value = null;
    resetBonusForm();
    bonusForm.paycheck_year_id = props.paycheckYear.id;
    bonusForm.type = defaultBonusType;
    showBonusModal.value = true;
}

function openEditBonus(bonus: Bonus) {
    editingBonus.value = bonus;
    bonusForm.clearErrors();
    bonusForm.paycheck_year_id = bonus.paycheck_year_id;
    bonusForm.type = bonus.type;
    bonusForm.amount = bonus.amount;
    bonusForm.taxable = bonus.taxable;
    bonusForm.paid_tax = bonus.taxable ? bonus.paid_tax : '';
    bonusForm.description = bonus.description ?? '';
    bonusForm.paid_at = bonus.paid_at ?? '';
    showBonusModal.value = true;
}

function submitBonus() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            resetBonusForm();
            showBonusModal.value = false;
            editingBonus.value = null;
        },
    };

    if (editingBonus.value) {
        bonusForm.put(bonusUpdate.url(editingBonus.value.id), options);

        return;
    }

    bonusForm.post(bonusStore.url(), options);
}

function deleteBonus(bonus: Bonus) {
    if (!confirm('Ste prepričani, da želite izbrisati ta bonus?')) {
return;
}

    router.delete(bonusDestroy.url(bonus.id), { preserveScroll: true });
}

watch(() => bonusForm.taxable, (taxable) => {
    if (!taxable) {
        bonusForm.paid_tax = '';
    }
});

// New year modal state
const showYearModal = ref(false);
const yearForm = useForm({
    employee: props.employee,
    year: String(new Date().getFullYear()),
    child1_months: '12',
    child2_months: '12',
    child3_months: '0',
});

function openNewYear() {
    yearForm.employee = props.employee;
    yearForm.year = String(new Date().getFullYear());
    showYearModal.value = true;
}

function submitYear() {
    yearForm.post(yearStore.url(), {
        onSuccess: () => {
            showYearModal.value = false;
        },
    });
}

// Child months editing
const showChildModal = ref(false);
const childForm = useForm({
    child1_months: '0',
    child2_months: '0',
    child3_months: '0',
});

function openEditChildren() {
    if (!props.paycheckYear) {
return;
}

    childForm.child1_months = String(props.paycheckYear.child1_months);
    childForm.child2_months = String(props.paycheckYear.child2_months);
    childForm.child3_months = String(props.paycheckYear.child3_months);
    showChildModal.value = true;
}

function submitChildren() {
    if (!props.paycheckYear) {
return;
}

    childForm.put(yearUpdate.url(props.paycheckYear.id), {
        preserveScroll: true,
        onSuccess: () => {
            showChildModal.value = false;
        },
    });
}

function switchYear(value: AcceptableValue) {
    if (value === null || value === undefined) {
        return;
    }

    router.get(placeIndex.url(props.employee, { query: { year: String(value) } }), {}, { preserveState: true });
}

// Build all 12 months for display
const monthRows = computed(() => {
    return Array.from({ length: 12 }, (_, i) => {
        const month = i + 1;
        const paycheck = props.paychecks.find((p) => p.month === month);

        return { month, name: monthNames[i], paycheck };
    });
});
</script>

<template>
    <Head :title="`Plače – ${employeeLabel} ${year}`" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <Heading :title="`Plače – ${employeeLabel}`" description="Pregled in vnos mesečnih plač" />

            <div class="flex items-center gap-2">
                <Select :model-value="String(year)" @update:model-value="switchYear">
                    <SelectTrigger class="w-[120px]">
                        <SelectValue placeholder="Leto" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="y in availableYears" :key="y" :value="String(y)">
                            {{ y }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <Button variant="outline" size="sm" @click="openNewYear">Novo leto</Button>
            </div>
        </div>

        <template v-if="paycheckYear">
            <!-- Tax settings warning -->
            <div v-if="calculation && !calculation.has_tax_settings" class="rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800 dark:border-yellow-700 dark:bg-yellow-900/20 dark:text-yellow-200">
                Za leto {{ year }} ni davčnih nastavitev. Izračun dohodnine ni mogoč.
            </div>

            <!-- Paychecks table -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Mesečne plače</CardTitle>
                    <Button size="sm" @click="openAddPaycheck" :disabled="availableMonths.length === 0">
                        Dodaj plačo
                    </Button>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Mesec</TableHead>
                                <TableHead class="text-right">Neto</TableHead>
                                <TableHead class="text-right">Bruto</TableHead>
                                <TableHead class="text-right">Prispevki</TableHead>
                                <TableHead class="text-right">Davki</TableHead>
                                <TableHead class="text-right">Akcije</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="row in monthRows" :key="row.month" :class="{ 'text-muted-foreground': !row.paycheck }">
                                <TableCell>{{ row.name }}</TableCell>
                                <template v-if="row.paycheck">
                                    <TableCell class="text-right">{{ formatOptionalNumber(row.paycheck.net) }}<span v-if="row.paycheck.net !== null"> €</span></TableCell>
                                    <TableCell class="text-right">{{ formatNumber(row.paycheck.gross) }} €</TableCell>
                                    <TableCell class="text-right">{{ formatNumber(row.paycheck.contributions) }} €</TableCell>
                                    <TableCell class="text-right">{{ formatNumber(row.paycheck.taxes) }} €</TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button variant="ghost" size="sm" @click="openEditPaycheck(row.paycheck!)">Uredi</Button>
                                            <Button variant="ghost" size="sm" class="text-destructive" @click="deletePaycheck(row.paycheck!)">Briši</Button>
                                        </div>
                                    </TableCell>
                                </template>
                                <template v-else>
                                    <TableCell class="text-right">–</TableCell>
                                    <TableCell class="text-right">–</TableCell>
                                    <TableCell class="text-right">–</TableCell>
                                    <TableCell class="text-right">–</TableCell>
                                    <TableCell class="text-right"></TableCell>
                                </template>
                            </TableRow>
                            <TableRow v-if="calculation" class="bg-muted/40 font-medium text-foreground hover:bg-muted/40">
                                <TableCell>{{ hasTaxableBonuses ? 'Skupaj (z obdavčljivimi bonusi)' : 'Skupaj' }}</TableCell>
                                <TableCell class="text-right">{{ formatNumber(calculation.sum_net) }} €</TableCell>
                                <TableCell class="text-right">{{ formatNumber(calculation.sum_gross) }} €</TableCell>
                                <TableCell class="text-right">{{ formatNumber(calculation.sum_contributions) }} €</TableCell>
                                <TableCell class="text-right">{{ formatNumber(calculation.sum_taxes) }} €</TableCell>
                                <TableCell class="text-right"></TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <!-- Bonuses -->
            <Card>
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Dodatki (regres, božičnica...)</CardTitle>
                    <Button size="sm" @click="openAddBonus">Dodaj</Button>
                </CardHeader>
                <CardContent>
                    <Table v-if="bonuses.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tip</TableHead>
                                <TableHead>Opis</TableHead>
                                <TableHead>Obdavčljiv</TableHead>
                                <TableHead class="text-right">Znesek</TableHead>
                                <TableHead class="text-right">Plačan davek</TableHead>
                                <TableHead class="text-right">Akcije</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="bonus in bonuses" :key="bonus.id">
                                <TableCell>{{ bonusTypeLabel(bonus.type) }}</TableCell>
                                <TableCell>{{ bonus.description || '–' }}</TableCell>
                                <TableCell>{{ bonus.taxable ? 'Da' : 'Ne' }}</TableCell>
                                <TableCell class="text-right">{{ formatNumber(bonus.amount) }} €</TableCell>
                                <TableCell class="text-right">{{ bonus.taxable ? `${formatNumber(bonus.paid_tax)} €` : '–' }}</TableCell>
                                <TableCell class="text-right">
                                    <div class="flex justify-end gap-1">
                                        <Button variant="ghost" size="sm" @click="openEditBonus(bonus)">Uredi</Button>
                                        <Button variant="ghost" size="sm" class="text-destructive" @click="deleteBonus(bonus)">Briši</Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="text-sm text-muted-foreground">Ni dodatkov.</p>
                </CardContent>
            </Card>

            <!-- Tax calculation summary -->
            <Card v-if="calculation && calculation.has_tax_settings">
                <CardHeader class="flex flex-row items-center justify-between">
                    <CardTitle>Izračun dohodnine {{ year }}</CardTitle>
                    <Button variant="outline" size="sm" @click="openEditChildren">Uredi olajšave otrok</Button>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="rounded-xl bg-sky-600 px-5 py-4 text-white shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/75">Letna ocena</p>
                                <p class="mt-2 text-3xl font-black">Skupaj</p>
                                <p class="mt-2 max-w-xl text-sm text-white/80">
                                    {{ projectionSummary(calculation) }}
                                </p>
                            </div>
                            <div class="grid gap-4 sm:grid-cols-3 sm:gap-6">
                                <div class="text-right">
                                    <p class="text-xs uppercase tracking-[0.2em] text-white/70">Bruto</p>
                                    <p class="mt-1 text-2xl font-black">{{ formatNumber(calculation.projection.sum_gross) }} €</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs uppercase tracking-[0.2em] text-white/70">Prispevki</p>
                                    <p class="mt-1 text-2xl font-black">{{ formatNumber(calculation.projection.sum_contributions) }} €</p>
                                </div>
                                <div class="text-right">
                                    <p class="text-xs uppercase tracking-[0.2em] text-white/70">Akontacija</p>
                                    <p class="mt-1 text-2xl font-black">{{ formatNumber(calculation.projection.sum_taxes) }} €</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                        <div class="rounded-xl border bg-muted/20 p-4">
                            <h3 class="text-lg font-semibold">Olajšave</h3>
                            <div class="mt-4 space-y-3">
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b border-border/60 pb-3">
                                    <div>
                                        <p class="font-medium">Splošna olajšava</p>
                                        <p class="text-sm text-muted-foreground">Letni znesek</p>
                                    </div>
                                    <p class="text-right text-lg font-semibold">{{ formatNumber(calculation.breakdown.general_relief) }} €</p>
                                </div>
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b border-border/60 pb-3">
                                    <div>
                                        <p class="font-medium">1. otrok</p>
                                        <p class="text-sm text-muted-foreground">{{ paycheckYear.child1_months }} / 12 mesecev</p>
                                    </div>
                                    <p class="text-right text-lg font-semibold">{{ formatNumber(calculation.breakdown.child_relief1) }} €</p>
                                </div>
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b border-border/60 pb-3">
                                    <div>
                                        <p class="font-medium">2. otrok</p>
                                        <p class="text-sm text-muted-foreground">{{ paycheckYear.child2_months }} / 12 mesecev</p>
                                    </div>
                                    <p class="text-right text-lg font-semibold">{{ formatNumber(calculation.breakdown.child_relief2) }} €</p>
                                </div>
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                                    <div>
                                        <p class="font-medium">3. otrok</p>
                                        <p class="text-sm text-muted-foreground">{{ paycheckYear.child3_months }} / 12 mesecev</p>
                                    </div>
                                    <p class="text-right text-lg font-semibold">{{ formatNumber(calculation.breakdown.child_relief3) }} €</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-xl border p-4">
                            <h3 class="text-lg font-semibold">Izračun</h3>
                            <div class="mt-4 space-y-3">
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                                    <p class="text-muted-foreground">Osnova</p>
                                    <p class="text-right text-lg font-semibold">{{ formatNumber(calculation.projection.osnova) }} €</p>
                                </div>
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                                    <p class="text-muted-foreground">Olajšave</p>
                                    <p class="text-right text-lg font-semibold">{{ formatNumber(calculation.projection.olajsave) }} €</p>
                                </div>
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3 border-b border-border/60 pb-3">
                                    <p class="text-muted-foreground">Davčna osnova</p>
                                    <p class="text-right text-lg font-semibold">{{ formatNumber(calculation.projection.davcna_osnova) }} €</p>
                                </div>
                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                                    <p class="text-muted-foreground">Obračunana dohodnina</p>
                                    <p class="text-right text-lg font-semibold">{{ formatNumber(calculation.projection.dohodnina) }} €</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 rounded-xl px-5 py-4 text-white shadow-sm sm:flex-row sm:items-end sm:justify-between"
                        :class="Number(calculation.projection.razlika) > 0 ? 'bg-red-600' : 'bg-emerald-600'"
                    >
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-white/75">Poračun ob koncu leta</p>
                            <p class="mt-2 text-2xl font-black">{{ settlementTitle(calculation) }}</p>
                        </div>
                        <p class="text-right text-4xl font-black">{{ formatNumber(calculation.projection.razlika) }} €</p>
                    </div>
                </CardContent>
            </Card>
        </template>

        <!-- No paycheck year -->
        <div v-else class="flex flex-col items-center gap-4 py-12">
            <p class="text-muted-foreground">Za {{ employeeLabel }} v letu {{ year }} ni odprtega plačilnega cikla.</p>
            <Button @click="openNewYear">Odpri novo leto</Button>
        </div>
    </div>

    <!-- Paycheck Modal -->
    <Dialog v-model:open="showPaycheckModal">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ editingPaycheck ? 'Uredi plačo' : 'Dodaj plačo' }}</DialogTitle>
                <DialogDescription>
                    {{ editingPaycheck ? `Urejanje plače za ${monthNames[(editingPaycheck.month) - 1]}` : 'Vnesite podatke o mesečni plači' }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitPaycheck" class="flex flex-col gap-4">
                <div v-if="!editingPaycheck" class="space-y-1.5">
                    <Label for="month">Mesec</Label>
                    <Select v-model="paycheckForm.month">
                        <SelectTrigger>
                            <SelectValue placeholder="Izberite mesec" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="m in availableMonths" :key="m" :value="String(m)">
                                {{ monthNames[m - 1] }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="paycheckForm.errors.month" />
                </div>
                <div class="space-y-1.5">
                    <Label for="net">Neto (neobvezno)</Label>
                    <Input id="net" v-model="paycheckForm.net" type="number" step="0.01" min="0" placeholder="Pustite prazno, če zneska nimate" />
                    <InputError :message="paycheckForm.errors.net" />
                </div>
                <div class="space-y-1.5">
                    <Label for="gross">Bruto</Label>
                    <Input id="gross" v-model="paycheckForm.gross" type="number" step="0.01" min="0" />
                    <InputError :message="paycheckForm.errors.gross" />
                </div>
                <div class="space-y-1.5">
                    <Label for="contributions">Prispevki</Label>
                    <Input id="contributions" v-model="paycheckForm.contributions" type="number" step="0.01" min="0" />
                    <InputError :message="paycheckForm.errors.contributions" />
                </div>
                <div class="space-y-1.5">
                    <Label for="taxes">Davki (akontacija)</Label>
                    <Input id="taxes" v-model="paycheckForm.taxes" type="number" step="0.01" min="0" />
                    <InputError :message="paycheckForm.errors.taxes" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showPaycheckModal = false">Prekliči</Button>
                    <Button type="submit" :disabled="paycheckForm.processing">Shrani</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Bonus Modal -->
    <Dialog v-model:open="showBonusModal">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ editingBonus ? 'Uredi dodatek' : 'Dodaj dodatek' }}</DialogTitle>
                <DialogDescription>
                    {{ editingBonus ? 'Posodobite podatke o dodatku.' : 'Vnesite podatke o dodatku (regres, božičnica...)' }}
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitBonus" class="flex flex-col gap-4">
                <div class="space-y-1.5">
                    <Label for="bonus-type">Tip</Label>
                    <Select v-model="bonusForm.type">
                        <SelectTrigger id="bonus-type">
                            <SelectValue placeholder="Izberite tip dodatka" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="option in bonusTypeOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="bonusForm.errors.type" />
                </div>
                <div class="space-y-1.5">
                    <Label for="bonus-amount">Znesek</Label>
                    <Input id="bonus-amount" v-model="bonusForm.amount" type="number" step="0.01" min="0" />
                    <InputError :message="bonusForm.errors.amount" />
                </div>
                <div class="space-y-1.5">
                    <Label for="bonus-taxable" class="flex items-center gap-3">
                        <Checkbox id="bonus-taxable" v-model="bonusForm.taxable" />
                        <span>Bonus je obdavčljiv</span>
                    </Label>
                </div>
                <div v-if="bonusForm.taxable" class="space-y-1.5">
                    <Label for="bonus-paid-tax">Plačan davek</Label>
                    <Input id="bonus-paid-tax" v-model="bonusForm.paid_tax" type="number" step="0.01" min="0" />
                    <InputError :message="bonusForm.errors.paid_tax" />
                </div>
                <div class="space-y-1.5">
                    <Label for="bonus-description">Opis (neobvezno)</Label>
                    <Input id="bonus-description" v-model="bonusForm.description" />
                    <InputError :message="bonusForm.errors.description" />
                </div>
                <div class="space-y-1.5">
                    <Label for="bonus-paid_at">Datum izplačila (neobvezno)</Label>
                    <Input id="bonus-paid_at" v-model="bonusForm.paid_at" type="date" />
                    <InputError :message="bonusForm.errors.paid_at" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showBonusModal = false">Prekliči</Button>
                    <Button type="submit" :disabled="bonusForm.processing">Shrani</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- New Year Modal -->
    <Dialog v-model:open="showYearModal">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Odpri novo leto</DialogTitle>
                <DialogDescription>Nastavite parametre za nov plačilni cikel</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitYear" class="flex flex-col gap-4">
                <div class="space-y-1.5">
                    <Label>Leto</Label>
                    <Input v-model="yearForm.year" type="number" min="2020" />
                    <InputError :message="yearForm.errors.year" />
                </div>
                <div class="space-y-1.5">
                    <Label>Meseci 1. otroka (0-12)</Label>
                    <Input v-model="yearForm.child1_months" type="number" min="0" max="12" />
                    <InputError :message="yearForm.errors.child1_months" />
                </div>
                <div class="space-y-1.5">
                    <Label>Meseci 2. otroka (0-12)</Label>
                    <Input v-model="yearForm.child2_months" type="number" min="0" max="12" />
                    <InputError :message="yearForm.errors.child2_months" />
                </div>
                <div class="space-y-1.5">
                    <Label>Meseci 3. otroka (0-12)</Label>
                    <Input v-model="yearForm.child3_months" type="number" min="0" max="12" />
                    <InputError :message="yearForm.errors.child3_months" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showYearModal = false">Prekliči</Button>
                    <Button type="submit" :disabled="yearForm.processing">Odpri</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Edit Children Modal -->
    <Dialog v-model:open="showChildModal">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>Uredi olajšave za otroke</DialogTitle>
                <DialogDescription>Koliko mesecev v letu se upošteva olajšava za vsakega otroka</DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitChildren" class="flex flex-col gap-4">
                <div class="space-y-1.5">
                    <Label>Meseci 1. otroka (0-12)</Label>
                    <Input v-model="childForm.child1_months" type="number" min="0" max="12" />
                    <InputError :message="childForm.errors.child1_months" />
                </div>
                <div class="space-y-1.5">
                    <Label>Meseci 2. otroka (0-12)</Label>
                    <Input v-model="childForm.child2_months" type="number" min="0" max="12" />
                    <InputError :message="childForm.errors.child2_months" />
                </div>
                <div class="space-y-1.5">
                    <Label>Meseci 3. otroka (0-12)</Label>
                    <Input v-model="childForm.child3_months" type="number" min="0" max="12" />
                    <InputError :message="childForm.errors.child3_months" />
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="showChildModal = false">Prekliči</Button>
                    <Button type="submit" :disabled="childForm.processing">Shrani</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
