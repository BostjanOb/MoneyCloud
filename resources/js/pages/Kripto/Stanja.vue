<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import {
    destroy as balanceDestroy,
    index as balancesIndex,
    store as balanceStore,
    update as balanceUpdate,
} from '@/actions/App/Http/Controllers/CryptoBalanceController';
import { index as dcaIndex } from '@/actions/App/Http/Controllers/CryptoDcaPurchaseController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatSlovenianNumber } from '@/lib/utils';

type ProviderOption = {
    id: number;
    slug: string;
    name: string;
};

type SymbolOption = {
    id: number;
    symbol: string;
    label: string;
    current_price: string;
};

type BalanceRow = {
    balance_id: number;
    provider_id: number;
    provider_name: string;
    provider_slug: string;
    symbol_id: number;
    symbol: string;
    current_price: string;
    manual_quantity: string;
    current_value: string;
};

type SymbolSummaryRow = {
    symbol: string;
    quantity: string;
    current_price: string;
    current_value: string;
    provider_count: number;
};

type Props = {
    providerOptions: ProviderOption[];
    symbolOptions: SymbolOption[];
    balanceRows: BalanceRow[];
    symbolSummary: SymbolSummaryRow[];
};

const props = defineProps<Props>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Kripto',
            href: balancesIndex.url(),
        },
        {
            title: 'Stanja',
            href: balancesIndex.url(),
        },
    ],
});

const showBalanceModal = ref(false);
const editingBalance = ref<BalanceRow | null>(null);

const balanceForm = useForm({
    investment_provider_id: '',
    investment_symbol_id: '',
    manual_quantity: '0',
});

const totals = computed(() => ({
    currentValue: props.balanceRows.reduce(
        (sum, row) => sum + Number(row.current_value),
        0,
    ),
}));

function formatMoney(value: string | number): string {
    return `${formatSlovenianNumber(value)} €`;
}

function formatQuantity(value: string | number): string {
    return new Intl.NumberFormat('sl-SI', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 8,
    }).format(Number(value));
}

function resetBalanceForm(): void {
    balanceForm.defaults({
        investment_provider_id:
            props.providerOptions[0] !== undefined
                ? String(props.providerOptions[0].id)
                : '',
        investment_symbol_id:
            props.symbolOptions[0] !== undefined
                ? String(props.symbolOptions[0].id)
                : '',
        manual_quantity: '0',
    });
    balanceForm.reset();
    balanceForm.clearErrors();
}

function openCreateBalance(row?: BalanceRow): void {
    editingBalance.value = null;
    resetBalanceForm();

    if (row) {
        balanceForm.investment_provider_id = String(row.provider_id);
        balanceForm.investment_symbol_id = String(row.symbol_id);
    }

    showBalanceModal.value = true;
}

function openEditBalance(row: BalanceRow): void {
    editingBalance.value = row;
    balanceForm.clearErrors();
    balanceForm.investment_provider_id = String(row.provider_id);
    balanceForm.investment_symbol_id = String(row.symbol_id);
    balanceForm.manual_quantity = row.manual_quantity;
    showBalanceModal.value = true;
}

function updateProvider(value: AcceptableValue): void {
    if (typeof value === 'string') {
        balanceForm.investment_provider_id = value;
    }
}

function updateSymbol(value: AcceptableValue): void {
    if (typeof value === 'string') {
        balanceForm.investment_symbol_id = value;
    }
}

function submitBalance(): void {
    balanceForm.transform((data) => ({
        investment_provider_id: Number(data.investment_provider_id),
        investment_symbol_id: Number(data.investment_symbol_id),
        manual_quantity: data.manual_quantity,
    }));

    if (editingBalance.value) {
        balanceForm.put(balanceUpdate.url(editingBalance.value.balance_id), {
            preserveScroll: true,
            onSuccess: () => {
                showBalanceModal.value = false;
                resetBalanceForm();
            },
        });

        return;
    }

    balanceForm.post(balanceStore.url(), {
        preserveScroll: true,
        onSuccess: () => {
            showBalanceModal.value = false;
            resetBalanceForm();
        },
    });
}

function deleteBalance(row: BalanceRow): void {
    if (
        !confirm(
            `Ste prepričani, da želite izbrisati ročno stanje ${row.symbol}?`,
        )
    ) {
        return;
    }

    router.delete(balanceDestroy.url(row.balance_id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Kripto - Stanja" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <Heading
                title="Kripto stanja"
                description="Trenutne količine kripta po platformah."
            />
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" size="sm">
                    <Link :href="dcaIndex.url()">DCA transakcije</Link>
                </Button>
                <Button
                    size="sm"
                    :disabled="
                        providerOptions.length === 0 ||
                        symbolOptions.length === 0
                    "
                    @click="openCreateBalance()"
                >
                    Dodaj ročno stanje
                </Button>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <Card>
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        Trenutna vrednost
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p class="text-2xl font-semibold">
                        {{ formatMoney(totals.currentValue) }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Povzetek po simbolu</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table v-if="symbolSummary.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Simbol</TableHead>
                            <TableHead numeric class="text-right"
                                >Količina</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Cena</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Vrednost</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Platforme</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="row in symbolSummary"
                            :key="row.symbol"
                        >
                            <TableCell class="font-medium">
                                {{ row.symbol }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatQuantity(row.quantity) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(row.current_price) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(row.current_value) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ row.provider_count }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p v-else class="text-sm text-muted-foreground">
                    Povzetek po simbolih bo prikazan, ko dodate prva stanja.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Stanja po platformah</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table v-if="balanceRows.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Platforma</TableHead>
                            <TableHead>Simbol</TableHead>
                            <TableHead numeric class="text-right"
                                >Količina</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Cena</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Vrednost</TableHead
                            >
                            <TableHead class="text-right">Akcije</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="row in balanceRows"
                            :key="`${row.provider_id}:${row.symbol_id}`"
                        >
                            <TableCell class="font-medium">
                                {{ row.provider_name }}
                            </TableCell>
                            <TableCell>{{ row.symbol }}</TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatQuantity(row.manual_quantity) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(row.current_price) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(row.current_value) }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditBalance(row)"
                                    >
                                        Uredi
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deleteBalance(row)"
                                    >
                                        Briši
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p
                    v-else
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    Ni še vnesenih kripto stanj.
                </p>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="showBalanceModal">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        editingBalance
                            ? 'Uredi ročno stanje'
                            : 'Dodaj ročno stanje'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Vnesite trenutno količino kripta za izbrano platformo.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submitBalance">
                <div class="space-y-1.5">
                    <Label for="crypto-provider">Platforma</Label>
                    <Select
                        :model-value="balanceForm.investment_provider_id"
                        @update:model-value="updateProvider"
                    >
                        <SelectTrigger id="crypto-provider">
                            <SelectValue placeholder="Izberite platformo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="provider in providerOptions"
                                :key="provider.id"
                                :value="String(provider.id)"
                            >
                                {{ provider.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="balanceForm.errors.investment_provider_id"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="crypto-symbol">Simbol</Label>
                    <Select
                        :model-value="balanceForm.investment_symbol_id"
                        @update:model-value="updateSymbol"
                    >
                        <SelectTrigger id="crypto-symbol">
                            <SelectValue placeholder="Izberite simbol" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="symbol in symbolOptions"
                                :key="symbol.id"
                                :value="String(symbol.id)"
                            >
                                {{ symbol.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="balanceForm.errors.investment_symbol_id"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="manual-quantity">Ročna količina</Label>
                    <Input
                        id="manual-quantity"
                        v-model="balanceForm.manual_quantity"
                        type="number"
                        min="0"
                        step="0.00000001"
                    />
                    <InputError :message="balanceForm.errors.manual_quantity" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showBalanceModal = false"
                    >
                        Prekliči
                    </Button>
                    <Button type="submit" :disabled="balanceForm.processing">
                        Shrani
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
