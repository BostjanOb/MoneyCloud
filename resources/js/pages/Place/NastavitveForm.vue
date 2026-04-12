<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as placeIndex } from '@/actions/App/Http/Controllers/PaycheckController';
import {
    index as nastavitveIndex,
    store as settingStore,
    update as settingUpdate,
} from '@/actions/App/Http/Controllers/TaxSettingController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

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
    taxSetting: TaxSettingType | null;
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

const isEditing = computed(() => props.taxSetting !== null);

function defaultGeneralReliefBracket() {
    return {
        income_from: '0',
        income_to: '',
        base_relief: '',
        formula_constant: '',
        formula_multiplier: '',
    };
}

function defaultTaxBracket() {
    return {
        bracket_from: '0',
        bracket_to: '',
        base_tax: '0',
        rate: '',
    };
}

function createInitialForm() {
    if (!props.taxSetting) {
        return {
            year_from: '',
            year_to: '',
            general_relief_brackets: [defaultGeneralReliefBracket()],
            child_relief1: '',
            child_relief2: '',
            child_relief3: '',
            brackets: [defaultTaxBracket()],
        };
    }

    return {
        year_from: String(props.taxSetting.year_from),
        year_to: props.taxSetting.year_to
            ? String(props.taxSetting.year_to)
            : '',
        general_relief_brackets: props.taxSetting.general_relief_brackets.map(
            (bracket) => ({
                income_from: String(bracket.income_from),
                income_to:
                    bracket.income_to === null ? '' : String(bracket.income_to),
                base_relief: String(bracket.base_relief),
                formula_constant:
                    bracket.formula_constant === null
                        ? ''
                        : String(bracket.formula_constant),
                formula_multiplier:
                    bracket.formula_multiplier === null
                        ? ''
                        : String(bracket.formula_multiplier),
            }),
        ),
        child_relief1: props.taxSetting.child_relief1,
        child_relief2: props.taxSetting.child_relief2,
        child_relief3: props.taxSetting.child_relief3,
        brackets: props.taxSetting.brackets.map((bracket) => ({
            bracket_from: String(bracket.bracket_from),
            bracket_to:
                bracket.bracket_to === null ? '' : String(bracket.bracket_to),
            base_tax: String(bracket.base_tax),
            rate: String(bracket.rate),
        })),
    };
}

const form = useForm(createInitialForm());

function addGeneralReliefBracket() {
    form.general_relief_brackets.push({
        income_from: '',
        income_to: '',
        base_relief: '',
        formula_constant: '',
        formula_multiplier: '',
    });
}

function removeGeneralReliefBracket(index: number) {
    form.general_relief_brackets.splice(index, 1);
}

function addBracket() {
    form.brackets.push({
        bracket_from: '',
        bracket_to: '',
        base_tax: '',
        rate: '',
    });
}

function removeBracket(index: number) {
    form.brackets.splice(index, 1);
}

function submitSetting() {
    form.transform((data) => ({
        ...data,
        year_to: data.year_to || null,
        general_relief_brackets: data.general_relief_brackets.map(
            (bracket) => ({
                income_from: bracket.income_from,
                income_to: bracket.income_to || null,
                base_relief: bracket.base_relief,
                formula_constant: bracket.formula_constant || null,
                formula_multiplier: bracket.formula_multiplier || null,
            }),
        ),
        brackets: data.brackets.map((bracket) => ({
            bracket_from: bracket.bracket_from,
            bracket_to: bracket.bracket_to || null,
            base_tax: bracket.base_tax,
            rate: bracket.rate,
        })),
    }));

    if (props.taxSetting) {
        form.put(settingUpdate.url(props.taxSetting.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(settingStore.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head
        :title="
            isEditing ? 'Uredi davčno nastavitev' : 'Nova davčna nastavitev'
        "
    />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between"
        >
            <Heading
                :title="
                    isEditing
                        ? 'Uredi davčno nastavitev'
                        : 'Nova davčna nastavitev'
                "
                :description="
                    isEditing
                        ? 'Posodobite razrede splošne olajšave, olajšave za otroke in davčne razrede.'
                        : 'Dodajte novo obdobje davčnih nastavitev za obračun plač.'
                "
            />

            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="nastavitveIndex.url()"
                        >Nazaj na nastavitve</Link
                    >
                </Button>
                <Button @click="submitSetting" :disabled="form.processing">
                    {{ isEditing ? 'Shrani spremembe' : 'Shrani nastavitev' }}
                </Button>
            </div>
        </div>

        <form
            class="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(20rem,0.85fr)]"
            @submit.prevent="submitSetting"
        >
            <div class="space-y-5">
                <Card>
                    <CardHeader class="pb-4">
                        <CardTitle>Razredi splošne olajšave</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Za formulni razred izpolnite konstanto in
                            koeficient, sicer ostane razred fiksen.
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="(bracket, i) in form.general_relief_brackets"
                            :key="i"
                            class="grid gap-3 rounded-lg border bg-muted/20 p-3 md:grid-cols-2 xl:grid-cols-[0.9fr_0.9fr_1fr_1fr_1fr_auto]"
                        >
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Od</Label
                                >
                                <Input
                                    v-model="bracket.income_from"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `general_relief_brackets.${i}.income_from`
                                        ]
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Do</Label
                                >
                                <Input
                                    v-model="bracket.income_to"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Prazno = ∞"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `general_relief_brackets.${i}.income_to`
                                        ]
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Osnovna olajšava</Label
                                >
                                <Input
                                    v-model="bracket.base_relief"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0,00"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `general_relief_brackets.${i}.base_relief`
                                        ]
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Konstanta</Label
                                >
                                <Input
                                    v-model="bracket.formula_constant"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Neobvezno"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `general_relief_brackets.${i}.formula_constant`
                                        ]
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Koeficient</Label
                                >
                                <Input
                                    v-model="bracket.formula_multiplier"
                                    type="number"
                                    step="0.00001"
                                    min="0"
                                    placeholder="Neobvezno"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `general_relief_brackets.${i}.formula_multiplier`
                                        ]
                                    "
                                />
                            </div>
                            <div
                                class="flex items-start justify-end xl:items-end"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    :disabled="
                                        form.general_relief_brackets.length <= 1
                                    "
                                    @click="removeGeneralReliefBracket(i)"
                                >
                                    ×
                                </Button>
                            </div>
                            <InputError
                                :message="
                                    form.errors[`general_relief_brackets.${i}`]
                                "
                            />
                        </div>

                        <div
                            class="flex items-center justify-between gap-3 border-t pt-3"
                        >
                            <InputError
                                :message="form.errors.general_relief_brackets"
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addGeneralReliefBracket"
                                >Dodaj razred</Button
                            >
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-4">
                        <CardTitle>Davčni razredi</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Fiksni znesek in stopnja se uporabita po obstoječem
                            letnem obračunu dohodnine.
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div
                            v-for="(bracket, i) in form.brackets"
                            :key="i"
                            class="grid gap-3 rounded-lg border bg-muted/20 p-3 md:grid-cols-2 xl:grid-cols-[0.9fr_0.9fr_1fr_0.8fr_auto]"
                        >
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Od</Label
                                >
                                <Input
                                    v-model="bracket.bracket_from"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0"
                                />
                                <InputError
                                    :message="
                                        form.errors[
                                            `brackets.${i}.bracket_from`
                                        ]
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Do</Label
                                >
                                <Input
                                    v-model="bracket.bracket_to"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="Prazno = ∞"
                                />
                                <InputError
                                    :message="
                                        form.errors[`brackets.${i}.bracket_to`]
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Fiksni znesek</Label
                                >
                                <Input
                                    v-model="bracket.base_tax"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    placeholder="0,00"
                                />
                                <InputError
                                    :message="
                                        form.errors[`brackets.${i}.base_tax`]
                                    "
                                />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs text-muted-foreground"
                                    >Stopnja (%)</Label
                                >
                                <Input
                                    v-model="bracket.rate"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="0,00"
                                />
                                <InputError
                                    :message="form.errors[`brackets.${i}.rate`]"
                                />
                            </div>
                            <div
                                class="flex items-start justify-end xl:items-end"
                            >
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    class="text-destructive"
                                    :disabled="form.brackets.length <= 1"
                                    @click="removeBracket(i)"
                                >
                                    ×
                                </Button>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3 border-t pt-3"
                        >
                            <InputError :message="form.errors.brackets" />
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="addBracket"
                                >Dodaj razred</Button
                            >
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-5">
                <Card>
                    <CardHeader class="pb-4">
                        <CardTitle>Veljavnost</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Določite obdobje, za katero ta nastavitev velja.
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="space-y-1">
                            <Label>Od leta</Label>
                            <Input
                                v-model="form.year_from"
                                type="number"
                                min="2019"
                            />
                            <InputError :message="form.errors.year_from" />
                        </div>
                        <div class="space-y-1">
                            <Label>Do leta</Label>
                            <Input
                                v-model="form.year_to"
                                type="number"
                                min="2019"
                                placeholder="Prazno = še velja"
                            />
                            <InputError :message="form.errors.year_to" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-4">
                        <CardTitle>Olajšave za otroke</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Vnesite letne zneske, ki se nato sorazmerno
                            uporabijo po mesecih.
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-3">
                        <div class="space-y-1">
                            <Label>1. otrok</Label>
                            <Input
                                v-model="form.child_relief1"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <InputError :message="form.errors.child_relief1" />
                        </div>
                        <div class="space-y-1">
                            <Label>2. otrok</Label>
                            <Input
                                v-model="form.child_relief2"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <InputError :message="form.errors.child_relief2" />
                        </div>
                        <div class="space-y-1">
                            <Label>3. otrok</Label>
                            <Input
                                v-model="form.child_relief3"
                                type="number"
                                step="0.01"
                                min="0"
                            />
                            <InputError :message="form.errors.child_relief3" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent class="flex flex-col gap-3 pt-6">
                        <Button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full"
                        >
                            {{
                                isEditing
                                    ? 'Shrani spremembe'
                                    : 'Shrani nastavitev'
                            }}
                        </Button>
                        <Button
                            as-child
                            type="button"
                            variant="outline"
                            class="w-full"
                        >
                            <Link :href="nastavitveIndex.url()">Prekliči</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </form>
    </div>
</template>
