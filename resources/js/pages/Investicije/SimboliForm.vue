<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import { show as providerShow } from '@/actions/App/Http/Controllers/InvestmentProviderController';
import {
    index as symbolIndex,
    store as symbolStore,
    update as symbolUpdate,
} from '@/actions/App/Http/Controllers/InvestmentSymbolController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { AcceptableValue } from 'reka-ui';

type SymbolTypeOption = {
    value: string;
    label: string;
};

type SymbolFormData = {
    id: number;
    type: string;
    type_label: string;
    symbol: string;
    isin: string | null;
    taxable: boolean;
    price_source: string;
    current_price: string;
};

type Props = {
    symbol: SymbolFormData | null;
    typeOptions: SymbolTypeOption[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Investicije',
                href: providerShow.url('ibkr'),
            },
            {
                title: 'Simboli',
                href: symbolIndex.url(),
            },
        ],
    },
});

const isEditing = computed(() => props.symbol !== null);

const form = useForm({
    type: props.symbol?.type ?? props.typeOptions[0]?.value ?? 'etf',
    symbol: props.symbol?.symbol ?? '',
    isin: props.symbol?.isin ?? '',
    taxable: props.symbol?.taxable ?? true,
    price_source: props.symbol?.price_source ?? 'manual',
    current_price: props.symbol?.current_price ?? '0',
});

function updateType(value: AcceptableValue): void {
    if (typeof value === 'string') {
        form.type = value;
    }
}

function submitSymbol(): void {
    form.transform((data) => ({
        ...data,
        isin: data.isin || null,
    }));

    if (props.symbol) {
        form.put(symbolUpdate.url(props.symbol.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(symbolStore.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="isEditing ? 'Uredi simbol' : 'Nov simbol'" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                :title="isEditing ? 'Uredi simbol' : 'Nov simbol'"
                :description="
                    isEditing
                        ? 'Posodobite tip, obdavčitev in trenutno ceno simbola.'
                        : 'Dodajte simbol za uporabo pri investicijskih nakupih.'
                "
            />
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="symbolIndex.url()">Nazaj na simbole</Link>
                </Button>
                <Button @click="submitSymbol" :disabled="form.processing">
                    {{ isEditing ? 'Shrani spremembe' : 'Shrani simbol' }}
                </Button>
            </div>
        </div>

        <form class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]" @submit.prevent="submitSymbol">
            <Card>
                <CardHeader>
                    <CardTitle>Osnovni podatki</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="space-y-1.5">
                        <Label for="symbol-type">Tip</Label>
                        <Select
                            :model-value="form.type"
                            @update:model-value="updateType"
                        >
                            <SelectTrigger id="symbol-type">
                                <SelectValue placeholder="Izberite tip" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="option in typeOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.type" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="symbol-name">Simbol</Label>
                        <Input id="symbol-name" v-model="form.symbol" />
                        <InputError :message="form.errors.symbol" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="symbol-isin">ISIN</Label>
                        <Input id="symbol-isin" v-model="form.isin" />
                        <InputError :message="form.errors.isin" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="symbol-price-source">Vir cene</Label>
                        <Input
                            id="symbol-price-source"
                            v-model="form.price_source"
                        />
                        <InputError :message="form.errors.price_source" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Vrednotenje</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="space-y-1.5">
                        <Label for="symbol-current-price">Trenutna cena</Label>
                        <Input
                            id="symbol-current-price"
                            v-model="form.current_price"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="form.errors.current_price" />
                    </div>

                    <Label
                        for="symbol-taxable"
                        class="flex items-center gap-3 rounded-lg border p-3"
                    >
                        <Checkbox id="symbol-taxable" v-model="form.taxable" />
                        <span>Simbol je obdavčljiv</span>
                    </Label>
                    <InputError :message="form.errors.taxable" />
                </CardContent>
            </Card>
        </form>
    </div>
</template>
