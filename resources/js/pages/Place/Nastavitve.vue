<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { ref } from 'vue';
import {
    create as settingCreatePage,
    destroy as settingDestroy,
    edit as settingEditPage,
    index as nastavitveIndex,
} from '@/actions/App/Http/Controllers/TaxSettingController';
import { index as placeIndex } from '@/actions/App/Http/Controllers/PaycheckController';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatSlovenianNumber } from '@/lib/utils';

type GeneralReliefBracket = {
    income_from: string | number;
    income_to: string | number | null;
    base_relief: string | number;
    formula_constant: string | number | null;
    formula_multiplier: string | number | null;
};

type TaxBracket = {
    bracket_from: string | number;
    bracket_to: string | number | null;
    base_tax: string | number;
    rate: string | number;
};

type TaxSettingType = {
    id: number;
    year_from: number;
    year_to: number | null;
    general_relief_brackets: GeneralReliefBracket[];
    child_relief1: string;
    child_relief2: string;
    child_relief3: string;
    brackets: TaxBracket[];
};

type Props = {
    taxSettings: TaxSettingType[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Plače',
                href: placeIndex.url('bostjan'),
            },
            {
                title: 'Davčne nastavitve',
                href: nastavitveIndex.url(),
            },
        ],
    },
});

function formatNumber(value: string | number): string {
    return formatSlovenianNumber(value);
}

function formatBracketCount(count: number): string {
    if (count === 1) {
        return '1 davčni razred';
    }

    if (count === 2) {
        return '2 davčna razreda';
    }

    if (count <= 4) {
        return `${count} davčni razredi`;
    }

    return `${count} davčnih razredov`;
}

function formatGeneralReliefBracketCount(count: number): string {
    if (count === 1) {
        return '1 razred splošne olajšave';
    }

    if (count === 2) {
        return '2 razreda splošne olajšave';
    }

    if (count <= 4) {
        return `${count} razredi splošne olajšave`;
    }

    return `${count} razredov splošne olajšave`;
}

function formatGeneralReliefFormula(bracket: GeneralReliefBracket): string {
    if (bracket.formula_constant === null || bracket.formula_multiplier === null) {
        return `Fiksno: ${formatNumber(bracket.base_relief)} €`;
    }

    return `${formatNumber(bracket.base_relief)} + (${formatNumber(bracket.formula_constant)} - ${formatNumber(bracket.formula_multiplier)} × skupni dohodek)`;
}

function formatSettingSummary(setting: TaxSettingType): string {
    return `${formatGeneralReliefBracketCount(setting.general_relief_brackets.length)} · ${formatBracketCount(setting.brackets.length)}`;
}

function deleteSetting(setting: TaxSettingType) {
    if (!confirm('Ste prepričani, da želite izbrisati to nastavitev?')) {
        return;
    }

    router.delete(settingDestroy.url(setting.id), { preserveScroll: true });
}

function isSettingOpen(settingId: number, index: number): boolean {
    return openSettings.value[settingId] ?? index === 0;
}

function setSettingOpen(settingId: number, open: boolean): void {
    openSettings.value[settingId] = open;
}

const openSettings = ref<Record<number, boolean>>(
    Object.fromEntries(props.taxSettings.map((setting, index) => [setting.id, index === 0])),
);
</script>

<template>
    <Head title="Davčne nastavitve" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading title="Davčne nastavitve" description="Upravljanje davčnih razredov in olajšav" />
            <Button as-child size="sm">
                <Link :href="settingCreatePage.url()">Dodaj nastavitev</Link>
            </Button>
        </div>

        <Collapsible
            v-for="(setting, index) in taxSettings"
            :key="setting.id"
            :open="isSettingOpen(setting.id, index)"
            @update:open="(open) => setSettingOpen(setting.id, open)"
        >
            <Card class="overflow-hidden">
                <CardHeader class="gap-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0 flex-1 space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <CardTitle>
                                    {{ setting.year_from }}{{ setting.year_to ? ` – ${setting.year_to}` : ' –' }}
                                </CardTitle>
                                <span class="inline-flex items-center rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                                    {{ index === 0 ? 'Najnovejša nastavitev' : 'Starejša nastavitev' }}
                                </span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ formatSettingSummary(setting) }}
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <CollapsibleTrigger v-if="index > 0" as-child>
                                <Button variant="ghost" size="sm" class="gap-1.5">
                                    <ChevronRight
                                        class="size-4 transition-transform duration-200"
                                        :class="isSettingOpen(setting.id, index) ? 'rotate-90' : ''"
                                    />
                                    {{ isSettingOpen(setting.id, index) ? 'Skrij podrobnosti' : 'Pokaži podrobnosti' }}
                                </Button>
                            </CollapsibleTrigger>
                            <Button as-child variant="outline" size="sm">
                                <Link :href="settingEditPage.url(setting.id)">Uredi</Link>
                            </Button>
                            <Button variant="outline" size="sm" class="text-destructive" @click="deleteSetting(setting)">Briši</Button>
                        </div>
                    </div>
                </CardHeader>

                <CollapsibleContent>
                    <CardContent class="border-t pt-6">
                        <div class="mb-6 grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                            <div>
                                <span class="text-muted-foreground">Razredi splošne olajšave:</span>
                                <span class="ml-1 font-medium">{{ formatGeneralReliefBracketCount(setting.general_relief_brackets.length) }}</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">1. otrok:</span>
                                <span class="ml-1 font-medium">{{ formatNumber(setting.child_relief1) }} €</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">2. otrok:</span>
                                <span class="ml-1 font-medium">{{ formatNumber(setting.child_relief2) }} €</span>
                            </div>
                            <div>
                                <span class="text-muted-foreground">3. otrok:</span>
                                <span class="ml-1 font-medium">{{ formatNumber(setting.child_relief3) }} €</span>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">Razredi splošne olajšave</h3>
                                    <span class="text-xs text-muted-foreground">Spodnja meja je ekskluzivna, zgornja inkluzivna.</span>
                                </div>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Od (€)</TableHead>
                                            <TableHead>Do (€)</TableHead>
                                            <TableHead class="text-right">Osnovna olajšava (€)</TableHead>
                                            <TableHead class="text-right">Konstanta formule</TableHead>
                                            <TableHead class="text-right">Koeficient formule</TableHead>
                                            <TableHead>Pravilo</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(bracket, bracketIndex) in setting.general_relief_brackets" :key="bracketIndex">
                                            <TableCell>{{ formatNumber(bracket.income_from) }}</TableCell>
                                            <TableCell>{{ bracket.income_to === null ? '∞' : formatNumber(bracket.income_to) }}</TableCell>
                                            <TableCell class="text-right">{{ formatNumber(bracket.base_relief) }}</TableCell>
                                            <TableCell class="text-right">
                                                {{ bracket.formula_constant === null ? '–' : formatNumber(bracket.formula_constant) }}
                                            </TableCell>
                                            <TableCell class="text-right">
                                                {{ bracket.formula_multiplier === null ? '–' : formatNumber(bracket.formula_multiplier) }}
                                            </TableCell>
                                            <TableCell>{{ formatGeneralReliefFormula(bracket) }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>

                            <div>
                                <div class="mb-3 flex items-center justify-between">
                                    <h3 class="text-sm font-semibold">Davčni razredi</h3>
                                    <span class="text-xs text-muted-foreground">{{ formatBracketCount(setting.brackets.length) }}</span>
                                </div>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Od (€)</TableHead>
                                            <TableHead>Do (€)</TableHead>
                                            <TableHead class="text-right">Fiksni znesek (€)</TableHead>
                                            <TableHead class="text-right">Stopnja (%)</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="(bracket, bracketIndex) in setting.brackets" :key="bracketIndex">
                                            <TableCell>{{ formatNumber(bracket.bracket_from) }}</TableCell>
                                            <TableCell>{{ bracket.bracket_to ? formatNumber(bracket.bracket_to) : '∞' }}</TableCell>
                                            <TableCell class="text-right">{{ formatNumber(bracket.base_tax) }}</TableCell>
                                            <TableCell class="text-right">{{ formatNumber(bracket.rate) }} %</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </div>
                    </CardContent>
                </CollapsibleContent>
            </Card>
        </Collapsible>

        <div v-if="taxSettings.length === 0" class="py-12 text-center text-muted-foreground">
            Ni davčnih nastavitev. Dodajte prvo nastavitev.
        </div>
    </div>
</template>
