<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import {
    show as providerShow,
    update as providerUpdate,
} from '@/actions/App/Http/Controllers/InvestmentProviderController';
import {
    destroy as purchaseDestroy,
    store as purchaseStore,
    update as purchaseUpdate,
} from '@/actions/App/Http/Controllers/InvestmentPurchaseController';
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

type Provider = {
    id: number;
    slug: string;
    name: string;
    linked_savings_account_id: number | null;
    linked_savings_account_name: string | null;
    linked_savings_account_balance: string | null;
    requires_linked_savings_account: boolean;
};

type Summary = {
    total_invested: string;
    current_value: string;
    profit_loss: string;
    profit_loss_after_tax: string;
    total_fees: string;
    purchase_count: number;
};

type SymbolSummaryRow = {
    symbol: string;
    type_label: string;
    current_value: string;
    return_percentage: string;
    quantity: string;
    total_invested: string;
    profit_loss: string;
};

type SymbolOption = {
    id: number;
    symbol: string;
    type: string;
    type_label: string;
    label: string;
    current_price: string;
    taxable: boolean;
};

type SavingsAccountOption = {
    id: number;
    label: string;
    amount: string;
};

type Purchase = {
    id: number;
    investment_symbol_id: number;
    purchased_at: string;
    quantity: string;
    price_per_unit: string;
    fee: string;
    yield: string | null;
    coupon_date: string | null;
    expiry_date: string | null;
    price: string;
    current_value: string;
    unit_diff_percentage: string;
    profit_loss: string;
    profit_loss_after_tax: string;
    symbol: {
        id: number;
        symbol: string;
        type: string;
        type_label: string;
        taxable: boolean;
        current_price: string;
        price_source: string;
    };
};

type Props = {
    provider: Provider;
    summary: Summary;
    symbolSummary: SymbolSummaryRow[];
    purchases: Purchase[];
    symbolOptions: SymbolOption[];
    savingsAccountOptions: SavingsAccountOption[];
};

const props = defineProps<Props>();

setLayoutProps({
    breadcrumbs: [
        {
            title: 'Investicije',
            href: providerShow.url(props.provider.slug),
        },
    ],
});

const NO_SAVINGS_ACCOUNT = '__none__';

const showPurchaseModal = ref(false);
const showSavingsModal = ref(false);
const editingPurchase = ref<Purchase | null>(null);

const purchaseForm = useForm({
    investment_symbol_id: '',
    purchased_at: localNowForInput(),
    quantity: '',
    price_per_unit: '',
    fee: '0',
    yield: '',
    coupon_date: '',
    expiry_date: '',
});

const savingsForm = useForm({
    linked_savings_account_id: props.provider.linked_savings_account_id
        ? String(props.provider.linked_savings_account_id)
        : NO_SAVINGS_ACCOUNT,
});

const selectedSymbol = computed(() =>
    props.symbolOptions.find(
        (option) => String(option.id) === purchaseForm.investment_symbol_id,
    ),
);

const isBondPurchase = computed(() => selectedSymbol.value?.type === 'bond');
const canCreatePurchase = computed(
    () =>
        !props.provider.requires_linked_savings_account ||
        props.provider.linked_savings_account_id !== null,
);

const summaryCards = computed(() => [
    { label: 'Skupaj vloženo', value: props.summary.total_invested },
    { label: 'Trenutna vrednost', value: props.summary.current_value },
    { label: 'Skupni P/L', value: props.summary.profit_loss },
    { label: 'P/L po davku', value: props.summary.profit_loss_after_tax },
    { label: 'Skupne provizije', value: props.summary.total_fees },
]);

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

function resetPurchaseForm(): void {
    purchaseForm.defaults({
        investment_symbol_id: '',
        purchased_at: localNowForInput(),
        quantity: '',
        price_per_unit: '',
        fee: '0',
        yield: '',
        coupon_date: '',
        expiry_date: '',
    });
    purchaseForm.reset();
    purchaseForm.clearErrors();
}

function openCreatePurchase(): void {
    editingPurchase.value = null;
    resetPurchaseForm();
    purchaseForm.investment_symbol_id =
        props.symbolOptions[0] !== undefined
            ? String(props.symbolOptions[0].id)
            : '';
    showPurchaseModal.value = true;
}

function openEditPurchase(purchase: Purchase): void {
    editingPurchase.value = purchase;
    purchaseForm.clearErrors();
    purchaseForm.investment_symbol_id = String(purchase.investment_symbol_id);
    purchaseForm.purchased_at = toDatetimeLocalInput(purchase.purchased_at);
    purchaseForm.quantity = purchase.quantity;
    purchaseForm.price_per_unit = purchase.price_per_unit;
    purchaseForm.fee = purchase.fee;
    purchaseForm.yield = purchase.yield ?? '';
    purchaseForm.coupon_date = purchase.coupon_date ?? '';
    purchaseForm.expiry_date = purchase.expiry_date ?? '';
    showPurchaseModal.value = true;
}

function updateSymbol(value: AcceptableValue): void {
    if (typeof value === 'string') {
        purchaseForm.investment_symbol_id = value;
    }
}

function updateLinkedSavingsAccount(value: AcceptableValue): void {
    if (typeof value === 'string') {
        savingsForm.linked_savings_account_id = value;
    }
}

function submitPurchase(): void {
    purchaseForm.transform((data) => ({
        investment_symbol_id: Number(data.investment_symbol_id),
        purchased_at: data.purchased_at,
        quantity: data.quantity,
        price_per_unit: data.price_per_unit,
        fee: data.fee,
        yield: isBondPurchase.value ? data.yield || null : null,
        coupon_date: isBondPurchase.value ? data.coupon_date || null : null,
        expiry_date: isBondPurchase.value ? data.expiry_date || null : null,
    }));

    if (editingPurchase.value) {
        purchaseForm.put(
            purchaseUpdate.url({
                investmentProvider: props.provider.slug,
                investmentPurchase: editingPurchase.value.id,
            }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    showPurchaseModal.value = false;
                    resetPurchaseForm();
                },
            },
        );

        return;
    }

    purchaseForm.post(purchaseStore.url(props.provider.slug), {
        preserveScroll: true,
        onSuccess: () => {
            showPurchaseModal.value = false;
            resetPurchaseForm();
        },
    });
}

function submitLinkedSavingsAccount(): void {
    savingsForm.transform((data) => ({
        linked_savings_account_id:
            data.linked_savings_account_id === NO_SAVINGS_ACCOUNT
                ? null
                : Number(data.linked_savings_account_id),
    }));

    savingsForm.put(providerUpdate.url(props.provider.slug), {
        preserveScroll: true,
        onSuccess: () => {
            showSavingsModal.value = false;
        },
    });
}

function deletePurchase(purchase: Purchase): void {
    if (!confirm('Ste prepričani, da želite izbrisati ta nakup?')) {
        return;
    }

    purchaseForm.delete(
        purchaseDestroy.url({
            investmentProvider: props.provider.slug,
            investmentPurchase: purchase.id,
        }),
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="`Investicije – ${provider.name}`" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="rounded-2xl bg-linear-to-br from-sky-700 via-sky-600 to-cyan-600 p-6 text-white shadow-sm"
        >
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"
            >
                <div class="space-y-2">
                    <Heading
                        class="[&_h2]:text-white [&_p]:text-sky-50/90"
                        :title="`Investicije – ${provider.name}`"
                        description="Pregled nakupov, trenutnih cen in davčne ocene."
                    />
                    <p class="text-sm text-sky-50/90">
                        Skupaj vnosov: {{ summary.purchase_count }}
                    </p>
                    <p
                        v-if="
                            provider.requires_linked_savings_account &&
                            provider.linked_savings_account_name
                        "
                        class="text-sm text-sky-50/90"
                    >
                        Povezan račun:
                        {{ provider.linked_savings_account_name }}
                        <span
                            v-if="
                                provider.linked_savings_account_balance !== null
                            "
                        >
                            ({{
                                formatMoney(
                                    provider.linked_savings_account_balance,
                                )
                            }})
                        </span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="provider.requires_linked_savings_account"
                        variant="secondary"
                        size="sm"
                        @click="showSavingsModal = true"
                    >
                        {{
                            provider.linked_savings_account_id === null
                                ? 'Poveži račun'
                                : 'Uredi povezavo'
                        }}
                    </Button>
                    <Button
                        size="sm"
                        :disabled="
                            !canCreatePurchase || symbolOptions.length === 0
                        "
                        @click="openCreatePurchase"
                    >
                        Dodaj nakup
                    </Button>
                </div>
            </div>
        </div>

        <div
            v-if="
                provider.requires_linked_savings_account &&
                provider.linked_savings_account_id === null
            "
            class="rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-800"
        >
            Pred vnosom nakupa pri ponudniku {{ provider.name }} najprej
            povežite leaf varčevalni račun, ker se bo ob vsakem nakupu stanje
            samodejno zmanjšalo.
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <Card
                v-for="card in summaryCards"
                :key="card.label"
                class="border-border/60"
            >
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm text-muted-foreground">
                        {{ card.label }}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <p
                        class="text-2xl font-semibold tracking-tight"
                        :class="
                            card.label.includes('P/L')
                                ? valueTone(card.value)
                                : ''
                        "
                    >
                        {{
                            card.label.includes('P/L')
                                ? formatSignedMoney(card.value)
                                : formatMoney(card.value)
                        }}
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
                                >Nakup</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Količina</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Donos</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Trenutno</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >P/L</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="row in symbolSummary"
                            :key="row.symbol"
                        >
                            <TableCell>
                                <div class="flex flex-col gap-1">
                                    <span class="font-medium">{{
                                        row.symbol
                                    }}</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ row.type_label }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(row.total_invested) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatQuantity(row.quantity) }}
                            </TableCell>
                            <TableCell
                                numeric
                                class="text-right"
                                :class="valueTone(row.return_percentage)"
                            >
                                {{ formatPercent(row.return_percentage) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(row.current_value) }}
                            </TableCell>

                            <TableCell
                                numeric
                                class="text-right"
                                :class="valueTone(row.profit_loss)"
                            >
                                {{ formatSignedMoney(row.profit_loss) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p v-else class="text-sm text-muted-foreground">
                    Povzetek po simbolih bo prikazan, ko dodate prve nakupe.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Nakupi</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table v-if="purchases.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Datum</TableHead>
                            <TableHead>Simbol</TableHead>
                            <TableHead numeric class="text-right"
                                >Količina</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Cena/enoto</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Cena</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Provizija</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Razlika/enoto</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >P/L</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >P/L po davku</TableHead
                            >
                            <TableHead class="text-right">Akcije</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="purchase in purchases"
                            :key="purchase.id"
                        >
                            <TableCell>{{
                                formatDateTime(purchase.purchased_at)
                            }}</TableCell>
                            <TableCell>
                                <div class="flex flex-col gap-1">
                                    <span class="font-medium">
                                        {{ purchase.symbol.symbol }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ purchase.symbol.type_label }}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatQuantity(purchase.quantity) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(purchase.price_per_unit) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(purchase.price) }}
                            </TableCell>
                            <TableCell numeric class="text-right">
                                {{ formatMoney(purchase.fee) }}
                            </TableCell>
                            <TableCell
                                numeric
                                class="text-right"
                                :class="
                                    valueTone(purchase.unit_diff_percentage)
                                "
                            >
                                {{
                                    formatPercent(purchase.unit_diff_percentage)
                                }}
                            </TableCell>
                            <TableCell
                                numeric
                                class="text-right"
                                :class="valueTone(purchase.profit_loss)"
                            >
                                {{ formatSignedMoney(purchase.profit_loss) }}
                            </TableCell>
                            <TableCell
                                numeric
                                class="text-right"
                                :class="
                                    valueTone(purchase.profit_loss_after_tax)
                                "
                            >
                                {{
                                    formatSignedMoney(
                                        purchase.profit_loss_after_tax,
                                    )
                                }}
                            </TableCell>
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditPurchase(purchase)"
                                    >
                                        Uredi
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deletePurchase(purchase)"
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
                    Ni še vnesenih nakupov za {{ provider.name }}.
                </p>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="showPurchaseModal">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        editingPurchase
                            ? 'Uredi investicijski nakup'
                            : 'Dodaj investicijski nakup'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Vnesite podrobnosti nakupa za ponudnika {{ provider.name }}.
                </DialogDescription>
            </DialogHeader>

            <form
                class="grid gap-4 md:grid-cols-2"
                @submit.prevent="submitPurchase"
            >
                <div class="space-y-1.5 md:col-span-2">
                    <Label for="purchase-symbol">Simbol</Label>
                    <Select
                        :model-value="purchaseForm.investment_symbol_id"
                        @update:model-value="updateSymbol"
                    >
                        <SelectTrigger id="purchase-symbol">
                            <SelectValue placeholder="Izberite simbol" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in symbolOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="purchaseForm.errors.investment_symbol_id"
                    />
                </div>

                <div class="space-y-1.5">
                    <Label for="purchase-datetime">Datum in ura</Label>
                    <Input
                        id="purchase-datetime"
                        v-model="purchaseForm.purchased_at"
                        type="datetime-local"
                    />
                    <InputError :message="purchaseForm.errors.purchased_at" />
                </div>

                <div class="space-y-1.5">
                    <Label for="purchase-quantity">Količina</Label>
                    <Input
                        id="purchase-quantity"
                        v-model="purchaseForm.quantity"
                        type="number"
                        min="0.00000001"
                        step="0.00000001"
                    />
                    <InputError :message="purchaseForm.errors.quantity" />
                </div>

                <div class="space-y-1.5">
                    <Label for="purchase-price-per-unit">Cena na enoto</Label>
                    <Input
                        id="purchase-price-per-unit"
                        v-model="purchaseForm.price_per_unit"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="purchaseForm.errors.price_per_unit" />
                </div>

                <div class="space-y-1.5">
                    <Label for="purchase-fee">Provizija</Label>
                    <Input
                        id="purchase-fee"
                        v-model="purchaseForm.fee"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="purchaseForm.errors.fee" />
                </div>

                <template v-if="isBondPurchase">
                    <div class="space-y-1.5">
                        <Label for="purchase-yield">Donos (%)</Label>
                        <Input
                            id="purchase-yield"
                            v-model="purchaseForm.yield"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="purchaseForm.errors.yield" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="purchase-coupon-date">Datum kupona</Label>
                        <Input
                            id="purchase-coupon-date"
                            v-model="purchaseForm.coupon_date"
                            type="date"
                        />
                        <InputError
                            :message="purchaseForm.errors.coupon_date"
                        />
                    </div>

                    <div class="space-y-1.5 md:col-span-2">
                        <Label for="purchase-expiry-date"
                            >Datum zapadlosti</Label
                        >
                        <Input
                            id="purchase-expiry-date"
                            v-model="purchaseForm.expiry_date"
                            type="date"
                        />
                        <InputError
                            :message="purchaseForm.errors.expiry_date"
                        />
                    </div>
                </template>

                <div
                    v-if="selectedSymbol"
                    class="rounded-lg border border-dashed border-border bg-muted/30 p-3 text-sm md:col-span-2"
                >
                    <p>
                        Trenutna cena:
                        <span class="font-medium">
                            {{ formatMoney(selectedSymbol.current_price) }}
                        </span>
                    </p>
                    <p class="text-muted-foreground">
                        Vir cene:
                        {{ selectedSymbol.type_label }}
                        {{
                            selectedSymbol.taxable
                                ? '• Obdavčljivo'
                                : '• Neobdavčljivo'
                        }}
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
                        {{
                            editingPurchase ? 'Shrani spremembe' : 'Dodaj nakup'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showSavingsModal">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Povezava z varčevalnim računom</DialogTitle>
                <DialogDescription>
                    Izberite leaf račun, iz katerega se bo nakupom pri ponudniku
                    {{ provider.name }} samodejno odštelo stanje.
                </DialogDescription>
            </DialogHeader>

            <form
                class="grid gap-4"
                @submit.prevent="submitLinkedSavingsAccount"
            >
                <div class="space-y-1.5">
                    <Label for="provider-savings-account"
                        >Varčevalni račun</Label
                    >
                    <Select
                        :model-value="savingsForm.linked_savings_account_id"
                        @update:model-value="updateLinkedSavingsAccount"
                    >
                        <SelectTrigger id="provider-savings-account">
                            <SelectValue placeholder="Brez povezave" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NO_SAVINGS_ACCOUNT">
                                Brez povezave
                            </SelectItem>
                            <SelectItem
                                v-for="option in savingsAccountOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ option.label }} ({{
                                    formatMoney(option.amount)
                                }})
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="savingsForm.errors.linked_savings_account_id"
                    />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showSavingsModal = false"
                    >
                        Prekliči
                    </Button>
                    <Button type="submit" :disabled="savingsForm.processing">
                        Shrani povezavo
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
