<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import { index as balancesIndex } from '@/actions/App/Http/Controllers/CryptoBalanceController';
import {
    destroy as dcaDestroy,
    index as dcaIndex,
    store as dcaStore,
    update as dcaUpdate,
} from '@/actions/App/Http/Controllers/CryptoDcaPurchaseController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
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

type DcaPurchase = {
    id: number;
    investment_provider_id: number;
    investment_symbol_id: number;
    purchased_at: string;
    transaction_type: 'buy' | 'sell';
    transaction_type_label: string;
    quantity: string;
    price_per_unit: string;
    fee: string;
    trade_value: string;
    net_amount: string;
    provider: ProviderOption;
    symbol: {
        id: number;
        symbol: string;
        current_price: string;
    };
};

type SymbolGroup = {
    symbol: {
        id: number;
        symbol: string;
        current_price: string;
    };
    summary: {
        quantity: string;
        buy_amount: string;
        current_value: string;
        profit_loss_amount: string;
        profit_loss_percentage: string;
        purchase_count: number;
    };
    purchases: DcaPurchase[];
};

type Props = {
    providerOptions: ProviderOption[];
    symbolOptions: SymbolOption[];
    symbolGroups: SymbolGroup[];
};

const props = defineProps<Props>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Kripto',
            href: balancesIndex.url(),
        },
        {
            title: 'DCA transakcije',
            href: dcaIndex.url(),
        },
    ],
});

const showPurchaseModal = ref(false);
const editingPurchase = ref<DcaPurchase | null>(null);
const activeSymbolId = ref(
    props.symbolGroups[0] !== undefined
        ? String(props.symbolGroups[0].symbol.id)
        : '',
);

const purchaseForm = useForm({
    investment_provider_id: '',
    investment_symbol_id: '',
    purchased_at: localNowForInput(),
    transaction_type: 'buy' as 'buy' | 'sell',
    quantity: '',
    price_per_unit: '',
    fee: '0',
    add_to_balance: false,
    balance_provider_id: '',
});

const activeGroup = computed(() =>
    props.symbolGroups.find(
        (group) => String(group.symbol.id) === activeSymbolId.value,
    ),
);

const selectedSymbol = computed(() =>
    props.symbolOptions.find(
        (symbol) => String(symbol.id) === purchaseForm.investment_symbol_id,
    ),
);

const isSellTransaction = computed(
    () => purchaseForm.transaction_type === 'sell',
);

const netAmountPreview = computed(() => {
    const quantity = Number(purchaseForm.quantity);
    const price = Number(purchaseForm.price_per_unit);
    const fee = Number(purchaseForm.fee);

    if (Number.isNaN(quantity) || Number.isNaN(price) || Number.isNaN(fee)) {
        return '0.00';
    }

    const grossValue = quantity * price;

    return (
        grossValue + (isSellTransaction.value ? -fee : fee)
    ).toFixed(2);
});

function localNowForInput(): string {
    return toDatetimeLocalInput(new Date().toISOString());
}

function toDatetimeLocalInput(value: string): string {
    const date = new Date(value);
    const parts = [
        date.getFullYear(),
        String(date.getMonth() + 1).padStart(2, '0'),
        String(date.getDate()).padStart(2, '0'),
    ];
    const time = [
        String(date.getHours()).padStart(2, '0'),
        String(date.getMinutes()).padStart(2, '0'),
    ];

    return `${parts.join('-')}T${time.join(':')}`;
}

function formatMoney(value: string | number): string {
    return `${formatSlovenianNumber(value)} €`;
}

function formatSignedMoney(value: string | number): string {
    const amount = Number(value);
    const prefix = amount > 0 ? '+' : '';

    return `${prefix}${formatSlovenianNumber(amount)} €`;
}

function formatQuantity(value: string | number): string {
    return new Intl.NumberFormat('sl-SI', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 8,
    }).format(Number(value));
}

function formatDateTime(value: string): string {
    return new Intl.DateTimeFormat('sl-SI', {
        dateStyle: 'short',
        timeStyle: 'short',
    }).format(new Date(value));
}

function formatPercent(value: string | number): string {
    const amount = Number(value);
    const prefix = amount > 0 ? '+' : '';

    return `${prefix}${formatSlovenianNumber(amount)} %`;
}

function valueTone(value: string | number): string {
    const amount = Number(value);

    if (amount > 0) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    if (amount < 0) {
        return 'text-destructive';
    }

    return 'text-foreground';
}

function resetPurchaseForm(symbolId?: number): void {
    purchaseForm.defaults({
        investment_provider_id:
            props.providerOptions[0] !== undefined
                ? String(props.providerOptions[0].id)
                : '',
        investment_symbol_id: symbolId
            ? String(symbolId)
            : activeSymbolId.value ||
              (props.symbolOptions[0] !== undefined
                  ? String(props.symbolOptions[0].id)
                  : ''),
        purchased_at: localNowForInput(),
        transaction_type: 'buy',
        quantity: '',
        price_per_unit: '',
        fee: '0',
        add_to_balance: false,
        balance_provider_id:
            props.providerOptions[0] !== undefined
                ? String(props.providerOptions[0].id)
                : '',
    });
    purchaseForm.reset();
    purchaseForm.clearErrors();
}

function openCreatePurchase(symbolId?: number): void {
    editingPurchase.value = null;
    resetPurchaseForm(symbolId);
    showPurchaseModal.value = true;
}

function openEditPurchase(purchase: DcaPurchase): void {
    editingPurchase.value = purchase;
    purchaseForm.clearErrors();
    purchaseForm.investment_provider_id = String(
        purchase.investment_provider_id,
    );
    purchaseForm.investment_symbol_id = String(purchase.investment_symbol_id);
    purchaseForm.purchased_at = toDatetimeLocalInput(purchase.purchased_at);
    purchaseForm.transaction_type = purchase.transaction_type;
    purchaseForm.quantity = purchase.quantity;
    purchaseForm.price_per_unit = purchase.price_per_unit;
    purchaseForm.fee = purchase.fee;
    purchaseForm.add_to_balance = false;
    purchaseForm.balance_provider_id = '';
    showPurchaseModal.value = true;
}

function updateProvider(value: AcceptableValue): void {
    if (typeof value === 'string') {
        const previousProviderId = purchaseForm.investment_provider_id;

        purchaseForm.investment_provider_id = value;

        if (
            purchaseForm.add_to_balance &&
            (purchaseForm.balance_provider_id === '' ||
                purchaseForm.balance_provider_id === previousProviderId)
        ) {
            purchaseForm.balance_provider_id = value;
        }
    }
}

function updateSymbol(value: AcceptableValue): void {
    if (typeof value === 'string') {
        purchaseForm.investment_symbol_id = value;
    }
}

function updateTransactionType(value: AcceptableValue): void {
    if (value === 'buy' || value === 'sell') {
        purchaseForm.transaction_type = value;
    }
}

function updateBalanceProvider(value: AcceptableValue): void {
    if (typeof value === 'string') {
        purchaseForm.balance_provider_id = value;
    }
}

function updateAddToBalance(value: boolean | 'indeterminate'): void {
    purchaseForm.add_to_balance = value === true;

    if (purchaseForm.add_to_balance) {
        purchaseForm.balance_provider_id = purchaseForm.investment_provider_id;
    }
}

function submitPurchase(): void {
    purchaseForm.transform((data) => ({
        investment_provider_id: Number(data.investment_provider_id),
        investment_symbol_id: Number(data.investment_symbol_id),
        purchased_at: data.purchased_at,
        transaction_type: data.transaction_type,
        quantity: data.quantity,
        price_per_unit: data.price_per_unit,
        fee: data.fee,
        add_to_balance: editingPurchase.value ? false : data.add_to_balance,
        balance_provider_id:
            !editingPurchase.value && data.add_to_balance
                ? Number(data.balance_provider_id)
                : null,
    }));

    if (editingPurchase.value) {
        purchaseForm.put(dcaUpdate.url(editingPurchase.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                activeSymbolId.value = purchaseForm.investment_symbol_id;
                showPurchaseModal.value = false;
                resetPurchaseForm();
            },
        });

        return;
    }

    purchaseForm.post(dcaStore.url(), {
        preserveScroll: true,
        onSuccess: () => {
            activeSymbolId.value = purchaseForm.investment_symbol_id;
            showPurchaseModal.value = false;
            resetPurchaseForm();
        },
    });
}

function deletePurchase(purchase: DcaPurchase): void {
    if (
        !confirm(
            `Ste prepričani, da želite izbrisati DCA transakcijo ${purchase.symbol.symbol}?`,
        )
    ) {
        return;
    }

    router.delete(dcaDestroy.url(purchase.id), { preserveScroll: true });
}
</script>

<template>
    <Head title="Kripto - DCA transakcije" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <Heading
                title="DCA transakcije"
                description="Nakupi in prodaje so razdeljeni po kripto simbolih."
            />
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" size="sm">
                    <Link :href="balancesIndex.url()">Stanja</Link>
                </Button>
                <Button
                    size="sm"
                    :disabled="
                        providerOptions.length === 0 ||
                        symbolOptions.length === 0
                    "
                    @click="openCreatePurchase(activeGroup?.symbol.id)"
                >
                    Dodaj transakcijo
                </Button>
            </div>
        </div>

        <Tabs
            v-if="symbolGroups.length > 0"
            v-model="activeSymbolId"
            class="gap-4"
        >
            <div class="overflow-x-auto">
                <TabsList>
                    <TabsTrigger
                        v-for="group in symbolGroups"
                        :key="group.symbol.id"
                        :value="String(group.symbol.id)"
                    >
                        {{ group.symbol.symbol }}
                    </TabsTrigger>
                </TabsList>
            </div>

            <TabsContent
                v-for="group in symbolGroups"
                :key="group.symbol.id"
                :value="String(group.symbol.id)"
                class="space-y-4"
            >
                <div class="grid gap-4 md:grid-cols-5">
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm text-muted-foreground">
                                Količina
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-2xl font-semibold">
                                {{ formatQuantity(group.summary.quantity) }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm text-muted-foreground">
                                Vložek
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-2xl font-semibold">
                                {{ formatMoney(group.summary.buy_amount) }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm text-muted-foreground">
                                Trenutna vrednost
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p class="text-2xl font-semibold">
                                {{ formatMoney(group.summary.current_value) }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm text-muted-foreground">
                                P/L v %
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                :class="[
                                    'text-2xl font-semibold',
                                    valueTone(
                                        group.summary.profit_loss_percentage,
                                    ),
                                ]"
                            >
                                {{
                                    formatPercent(
                                        group.summary.profit_loss_percentage,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="pb-2">
                            <CardTitle class="text-sm text-muted-foreground">
                                P/L v EUR
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p
                                :class="[
                                    'text-2xl font-semibold',
                                    valueTone(group.summary.profit_loss_amount),
                                ]"
                            >
                                {{
                                    formatSignedMoney(
                                        group.summary.profit_loss_amount,
                                    )
                                }}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader
                        class="flex flex-row items-center justify-between gap-4"
                    >
                        <CardTitle>
                            {{ group.symbol.symbol }} transakcije
                        </CardTitle>
                        <Button
                            size="sm"
                            :disabled="providerOptions.length === 0"
                            @click="openCreatePurchase(group.symbol.id)"
                        >
                            Dodaj {{ group.symbol.symbol }}
                        </Button>
                    </CardHeader>
                    <CardContent class="overflow-x-auto">
                        <Table v-if="group.purchases.length > 0">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Datum</TableHead>
                                    <TableHead>Tip</TableHead>
                                    <TableHead>Platforma</TableHead>
                                    <TableHead numeric class="text-right">
                                        Količina
                                    </TableHead>
                                    <TableHead numeric class="text-right">
                                        Cena/enoto
                                    </TableHead>
                                    <TableHead numeric class="text-right">
                                        Znesek
                                    </TableHead>
                                    <TableHead numeric class="text-right">
                                        Provizija
                                    </TableHead>
                                    <TableHead numeric class="text-right">
                                        Neto
                                    </TableHead>
                                    <TableHead class="text-right"
                                        >Akcije</TableHead
                                    >
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow
                                    v-for="purchase in group.purchases"
                                    :key="purchase.id"
                                >
                                    <TableCell>
                                        {{
                                            formatDateTime(
                                                purchase.purchased_at,
                                            )
                                        }}
                                    </TableCell>
                                    <TableCell>
                                        {{ purchase.transaction_type_label }}
                                    </TableCell>
                                    <TableCell class="font-medium">
                                        {{ purchase.provider.name }}
                                    </TableCell>
                                    <TableCell numeric class="text-right">
                                        {{ formatQuantity(purchase.quantity) }}
                                    </TableCell>
                                    <TableCell numeric class="text-right">
                                        {{
                                            formatMoney(purchase.price_per_unit)
                                        }}
                                    </TableCell>
                                    <TableCell numeric class="text-right">
                                        {{
                                            formatMoney(purchase.trade_value)
                                        }}
                                    </TableCell>
                                    <TableCell numeric class="text-right">
                                        {{ formatMoney(purchase.fee) }}
                                    </TableCell>
                                    <TableCell numeric class="text-right">
                                        {{ formatMoney(purchase.net_amount) }}
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                @click="
                                                    openEditPurchase(purchase)
                                                "
                                            >
                                                Uredi
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                class="text-destructive"
                                                @click="
                                                    deletePurchase(purchase)
                                                "
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
                            Ni še DCA transakcij za {{ group.symbol.symbol }}.
                        </p>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>

        <Card v-else>
            <CardContent class="py-8 text-center text-sm text-muted-foreground">
                Ni še DCA transakcij. Dodajte prvi nakup ali prodajo in po
                želji hkrati uskladite tudi kripto stanje.
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="showPurchaseModal">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        editingPurchase
                            ? 'Uredi DCA transakcijo'
                            : 'Dodaj DCA transakcijo'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Vnesite podrobnosti nakupa ali prodaje. Ob dodajanju lahko
                    količino uskladite tudi s kripto stanjem.
                </DialogDescription>
            </DialogHeader>

            <form
                class="grid gap-4 md:grid-cols-2"
                @submit.prevent="submitPurchase"
            >
                <div class="space-y-1.5">
                    <Label for="dca-provider">Platforma</Label>
                    <Select
                        :model-value="purchaseForm.investment_provider_id"
                        @update:model-value="updateProvider"
                    >
                        <SelectTrigger id="dca-provider">
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
                        :message="purchaseForm.errors.investment_provider_id"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="dca-symbol">Simbol</Label>
                    <Select
                        :model-value="purchaseForm.investment_symbol_id"
                        @update:model-value="updateSymbol"
                    >
                        <SelectTrigger id="dca-symbol">
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
                        :message="purchaseForm.errors.investment_symbol_id"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="dca-transaction-type">Tip transakcije</Label>
                    <Select
                        :model-value="purchaseForm.transaction_type"
                        @update:model-value="updateTransactionType"
                    >
                        <SelectTrigger id="dca-transaction-type">
                            <SelectValue placeholder="Izberite tip" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="buy">Nakup</SelectItem>
                            <SelectItem value="sell">Prodaja</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="purchaseForm.errors.transaction_type"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="dca-date">Datum in ura</Label>
                    <Input
                        id="dca-date"
                        v-model="purchaseForm.purchased_at"
                        type="datetime-local"
                    />
                    <InputError :message="purchaseForm.errors.purchased_at" />
                </div>

                <div class="space-y-1.5">
                    <Label for="dca-quantity">Količina</Label>
                    <Input
                        id="dca-quantity"
                        v-model="purchaseForm.quantity"
                        type="number"
                        min="0.00000001"
                        step="0.00000001"
                    />
                    <InputError :message="purchaseForm.errors.quantity" />
                </div>

                <div class="space-y-1.5">
                    <Label for="dca-price">Cena na enoto</Label>
                    <Input
                        id="dca-price"
                        v-model="purchaseForm.price_per_unit"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="purchaseForm.errors.price_per_unit" />
                </div>

                <div class="space-y-1.5">
                    <Label for="dca-fee">Provizija</Label>
                    <Input
                        id="dca-fee"
                        v-model="purchaseForm.fee"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="purchaseForm.errors.fee" />
                </div>

                <div v-if="!editingPurchase" class="space-y-1.5 md:col-span-2">
                    <Label
                        for="dca-add-to-balance"
                        class="flex items-center gap-3"
                    >
                        <Checkbox
                            id="dca-add-to-balance"
                            :model-value="purchaseForm.add_to_balance"
                            @update:model-value="updateAddToBalance"
                        />
                        <span>
                            {{
                                isSellTransaction
                                    ? 'Odštej količino od stanja'
                                    : 'Dodaj količino v stanje'
                            }}
                        </span>
                    </Label>
                    <InputError :message="purchaseForm.errors.add_to_balance" />
                </div>

                <div
                    v-if="!editingPurchase && purchaseForm.add_to_balance"
                    class="space-y-1.5 md:col-span-2"
                >
                    <Label for="dca-balance-provider">
                        Platforma za stanje
                    </Label>
                    <Select
                        :model-value="purchaseForm.balance_provider_id"
                        @update:model-value="updateBalanceProvider"
                    >
                        <SelectTrigger id="dca-balance-provider">
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
                        :message="purchaseForm.errors.balance_provider_id"
                    />
                </div>

                <div
                    class="rounded-lg border border-dashed border-border bg-muted/30 p-3 text-sm md:col-span-2"
                >
                    <p>
                        Neto:
                        <span class="font-medium">
                            {{ formatMoney(netAmountPreview) }}
                        </span>
                    </p>
                    <p v-if="selectedSymbol" class="text-muted-foreground">
                        Trenutna cena {{ selectedSymbol.symbol }}:
                        {{ formatMoney(selectedSymbol.current_price) }}
                    </p>
                </div>

                <DialogFooter class="md:col-span-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="showPurchaseModal = false"
                    >
                        Prekliči
                    </Button>
                    <Button type="submit" :disabled="purchaseForm.processing">
                        Shrani
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
