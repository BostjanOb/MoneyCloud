<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, ref } from 'vue';
import {
    index as savingsIndex,
    store as savingsStore,
    update as savingsUpdate,
    destroy as savingsDestroy,
} from '@/actions/App/Http/Controllers/SavingsAccountController';
import { store as savingsBalanceAdjustmentStore } from '@/actions/App/Http/Controllers/SavingsBalanceAdjustmentController';
import { store as savingsInterestStore } from '@/actions/App/Http/Controllers/SavingsInterestController';
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
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn, formatSlovenianNumber } from '@/lib/utils';

type AccountNode = {
    id: number;
    parent_id: number | null;
    name: string;
    person_id: number;
    person_label: string;
    amount: string;
    apy: string;
    sort_order: number;
    annual_yield: string;
    monthly_yield: string;
    has_children: boolean;
    children: AccountNode[];
};

type PersonOption = {
    value: string;
    label: string;
};

type LeafAccountOption = {
    id: number;
    label: string;
    amount: string;
};

type Totals = {
    amount: string;
    annual_yield: string;
    monthly_yield: string;
};

type Props = {
    accounts: AccountNode[];
    personOptions: PersonOption[];
    leafAccountOptions: LeafAccountOption[];
    totals: Totals;
};

type AccountRow = {
    account: AccountNode;
    isRoot: boolean;
    parentName: string | null;
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Varčevanje',
                href: savingsIndex.url(),
            },
        ],
    },
});

const NO_PARENT_VALUE = '__none__';
const NO_RELATED_ACCOUNT_VALUE = '__none__';
const defaultPerson = props.personOptions[0]?.value ?? '';

const showAccountModal = ref(false);
const showBalanceModal = ref(false);
const showInterestModal = ref(false);
const editingAccount = ref<AccountNode | null>(null);
const selectedBalanceAccount = ref<AccountNode | null>(null);
const selectedInterestAccount = ref<AccountNode | null>(null);

const accountForm = useForm({
    name: '',
    person_id: defaultPerson,
    amount: '0',
    apy: '0',
    sort_order: '0',
    parent_id: NO_PARENT_VALUE,
});

const interestForm = useForm({
    amount: '',
});

const balanceForm = useForm({
    operation: 'add',
    amount: '',
    related_account_id: NO_RELATED_ACCOUNT_VALUE,
});

const rootAccounts = computed(() => props.accounts);

const parentOptions = computed(() =>
    rootAccounts.value
        .filter((account) => account.id !== editingAccount.value?.id)
        .map((account) => ({
            value: String(account.id),
            label: account.name,
        })),
);

const accountRows = computed(() =>
    props.accounts.flatMap<AccountRow>((account) => [
        { account, isRoot: true, parentName: null },
        ...account.children.map((child) => ({
            account: child,
            isRoot: false,
            parentName: account.name,
        })),
    ]),
);

const relatedAccountOptions = computed(() =>
    props.leafAccountOptions.filter(
        (account) => account.id !== selectedBalanceAccount.value?.id,
    ),
);

const interestPreviewRows = computed(() => {
    const account = selectedInterestAccount.value;
    const totalInterestInCents = toCents(interestForm.amount);

    if (account === null || !account.has_children || totalInterestInCents < 0) {
        return [];
    }

    const children = account.children;
    const totalBalanceInCents = children.reduce(
        (sum, child) => sum + toCents(child.amount),
        0,
    );
    const lastChildId = children.at(-1)?.id;
    let distributed = 0;

    return children.map((child) => {
        let shareInCents = 0;

        if (child.id === lastChildId) {
            shareInCents = totalInterestInCents - distributed;
        } else if (totalBalanceInCents === 0) {
            shareInCents = Math.floor(
                totalInterestInCents / Math.max(children.length, 1),
            );
            distributed += shareInCents;
        } else {
            shareInCents = Math.floor(
                totalInterestInCents *
                    (toCents(child.amount) / totalBalanceInCents),
            );
            distributed += shareInCents;
        }

        return {
            id: child.id,
            name: child.name,
            amount: child.amount,
            share: fromCents(shareInCents),
            percentage:
                totalInterestInCents === 0
                    ? 0
                    : (shareInCents / totalInterestInCents) * 100,
        };
    });
});

const projectedLeafAmount = computed(() => {
    const account = selectedInterestAccount.value;

    if (account === null || account.has_children) {
        return null;
    }

    return fromCents(toCents(account.amount) + toCents(interestForm.amount));
});

const selectedRelatedAccount = computed(
    () =>
        relatedAccountOptions.value.find(
            (account) => String(account.id) === balanceForm.related_account_id,
        ) ?? null,
);

const balanceFlowHint = computed(() => {
    const targetAccount = selectedBalanceAccount.value;
    const relatedAccount = selectedRelatedAccount.value;

    if (targetAccount === null) {
        return '';
    }

    if (relatedAccount === null) {
        return balanceForm.operation === 'add'
            ? 'Če drugega računa ne izberete, bo znesek dodan kot zunanji priliv.'
            : 'Če drugega računa ne izberete, bo znesek odštet kot zunanji odliv.';
    }

    return balanceForm.operation === 'add'
        ? `Znesek bo prenesen iz računa ${relatedAccount.label} na račun ${targetAccount.name}.`
        : `Znesek bo prenesen iz računa ${targetAccount.name} na račun ${relatedAccount.label}.`;
});

function formatNumber(value: string | number): string {
    return formatSlovenianNumber(value);
}

function toCents(value: string | number): number {
    const parsedValue = Number(value);

    if (Number.isNaN(parsedValue)) {
        return 0;
    }

    return Math.round(parsedValue * 100);
}

function fromCents(value: number): string {
    return (value / 100).toFixed(2);
}

function resetAccountForm(): void {
    accountForm.defaults({
        name: '',
        person_id: defaultPerson,
        amount: '0',
        apy: '0',
        sort_order: '0',
        parent_id: NO_PARENT_VALUE,
    });
    accountForm.reset();
    accountForm.clearErrors();
}

function openCreateAccount(): void {
    editingAccount.value = null;
    resetAccountForm();
    showAccountModal.value = true;
}

function resetBalanceForm(): void {
    balanceForm.defaults({
        operation: 'add',
        amount: '',
        related_account_id: NO_RELATED_ACCOUNT_VALUE,
    });
    balanceForm.reset();
    balanceForm.clearErrors();
}

function openBalanceModal(account: AccountNode): void {
    selectedBalanceAccount.value = account;
    resetBalanceForm();
    showBalanceModal.value = true;
}

function openEditAccount(account: AccountNode): void {
    editingAccount.value = account;
    accountForm.clearErrors();
    accountForm.name = account.name;
    accountForm.person_id = String(account.person_id);
    accountForm.amount = account.amount;
    accountForm.apy = account.apy;
    accountForm.sort_order = String(account.sort_order);
    accountForm.parent_id =
        account.parent_id === null
            ? NO_PARENT_VALUE
            : String(account.parent_id);
    showAccountModal.value = true;
}

function submitAccount(): void {
    accountForm.transform((data) => {
        if (editingAccount.value?.has_children) {
            return {
                name: data.name,
                person_id: Number(data.person_id),
                apy: data.apy,
                sort_order: Number(data.sort_order),
            };
        }

        return {
            name: data.name,
            person_id: Number(data.person_id),
            amount: data.amount,
            apy: data.apy,
            sort_order: Number(data.sort_order),
            parent_id:
                data.parent_id === NO_PARENT_VALUE
                    ? null
                    : Number(data.parent_id),
        };
    });

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showAccountModal.value = false;
            editingAccount.value = null;
            resetAccountForm();
        },
    };

    if (editingAccount.value) {
        accountForm.put(savingsUpdate.url(editingAccount.value.id), options);

        return;
    }

    accountForm.post(savingsStore.url(), options);
}

function submitBalance(): void {
    const account = selectedBalanceAccount.value;

    if (account === null) {
        return;
    }

    balanceForm.transform((data) => ({
        operation: data.operation,
        amount: data.amount,
        related_account_id:
            data.related_account_id === NO_RELATED_ACCOUNT_VALUE
                ? null
                : Number(data.related_account_id),
    }));

    balanceForm.post(savingsBalanceAdjustmentStore.url(account.id), {
        preserveScroll: true,
        onSuccess: () => {
            showBalanceModal.value = false;
            selectedBalanceAccount.value = null;
            resetBalanceForm();
        },
    });
}

function deleteAccount(account: AccountNode): void {
    const confirmation = account.has_children
        ? 'Ste prepričani, da želite izbrisati ta račun in vse njegove podračune?'
        : 'Ste prepričani, da želite izbrisati ta račun?';

    if (!confirm(confirmation)) {
        return;
    }

    router.delete(savingsDestroy.url(account.id), {
        preserveScroll: true,
    });
}

function resetInterestForm(): void {
    interestForm.defaults({
        amount: '',
    });
    interestForm.reset();
    interestForm.clearErrors();
}

function openInterestModal(account: AccountNode): void {
    selectedInterestAccount.value = account;
    resetInterestForm();
    showInterestModal.value = true;
}

function submitInterest(): void {
    const account = selectedInterestAccount.value;

    if (account === null) {
        return;
    }

    interestForm.post(savingsInterestStore.url(account.id), {
        preserveScroll: true,
        onSuccess: () => {
            showInterestModal.value = false;
            selectedInterestAccount.value = null;
            resetInterestForm();
        },
    });
}

function updatePerson(value: AcceptableValue): void {
    if (typeof value === 'string') {
        accountForm.person_id = value;
    }
}

function updateParent(value: AcceptableValue): void {
    if (typeof value === 'string') {
        accountForm.parent_id = value;
    }
}

function updateBalanceOperation(value: AcceptableValue): void {
    if (value === 'add' || value === 'subtract') {
        balanceForm.operation = value;
    }
}

function updateRelatedAccount(value: AcceptableValue): void {
    if (typeof value === 'string') {
        balanceForm.related_account_id = value;
    }
}
</script>

<template>
    <Head title="Varčevanje" />

    <div class="flex flex-col gap-6 p-4">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <Heading
                title="Varčevalni računi"
                description="Pregled računov, podračunov in obresti"
            />
            <Button size="sm" @click="openCreateAccount">Dodaj račun</Button>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Pregled računov</CardTitle>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table v-if="accountRows.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Ime</TableHead>
                            <TableHead>Oseba</TableHead>
                            <TableHead numeric class="text-right"
                                >Znesek</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >APY</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Mesečni donos</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Letni donos</TableHead
                            >
                            <TableHead class="text-right">Akcije</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="row in accountRows"
                            :key="row.account.id"
                        >
                            <TableCell>
                                <div
                                    class="flex min-w-[240px] flex-col gap-1"
                                    :class="
                                        row.isRoot
                                            ? ''
                                            : 'relative pl-8 before:absolute before:top-1 before:bottom-1 before:left-3 before:w-px before:bg-border'
                                    "
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            :class="
                                                cn(
                                                    'font-medium',
                                                    row.isRoot
                                                        ? 'text-foreground'
                                                        : 'text-foreground/90',
                                                )
                                            "
                                        >
                                            {{ row.account.name }}
                                        </span>
                                    </div>
                                    <span
                                        v-if="!row.isRoot && row.parentName"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Podračun od {{ row.parentName }}.
                                    </span>
                                    <span
                                        v-if="row.account.has_children"
                                        class="text-xs text-muted-foreground"
                                    >
                                        Znesek določa vsota podračunov.
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell>{{
                                row.isRoot ? row.account.person_label : ''
                            }}</TableCell>
                            <TableCell numeric class="text-right"
                                >{{
                                    formatNumber(row.account.amount)
                                }}
                                €</TableCell
                            >
                            <TableCell numeric class="text-right"
                                >{{
                                    formatNumber(row.account.apy)
                                }}
                                %</TableCell
                            >
                            <TableCell numeric class="text-right"
                                >{{
                                    formatNumber(row.account.annual_yield)
                                }}
                                €</TableCell
                            >
                            <TableCell numeric class="text-right"
                                >{{
                                    formatNumber(row.account.monthly_yield)
                                }}
                                €</TableCell
                            >
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        v-if="!row.account.has_children"
                                        variant="ghost"
                                        size="sm"
                                        @click="openBalanceModal(row.account)"
                                        >Prilagodi</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditAccount(row.account)"
                                        >Uredi</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openInterestModal(row.account)"
                                        >Obresti</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deleteAccount(row.account)"
                                    >
                                        Briši
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                    <TableFooter>
                        <TableRow class="hover:bg-muted/50">
                            <TableCell class="font-semibold">Skupaj</TableCell>
                            <TableCell />
                            <TableCell numeric class="text-right font-semibold"
                                >{{ formatNumber(totals.amount) }} €</TableCell
                            >
                            <TableCell />
                            <TableCell numeric class="text-right font-semibold"
                                >{{
                                    formatNumber(totals.annual_yield)
                                }}
                                €</TableCell
                            >
                            <TableCell numeric class="text-right font-semibold"
                                >{{
                                    formatNumber(totals.monthly_yield)
                                }}
                                €</TableCell
                            >
                            <TableCell />
                        </TableRow>
                    </TableFooter>
                </Table>
                <p
                    v-else
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    Ni še varčevalnih računov. Dodajte prvega.
                </p>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="showAccountModal">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{
                    editingAccount ? 'Uredi račun' : 'Dodaj račun'
                }}</DialogTitle>
                <DialogDescription>
                    {{
                        editingAccount?.has_children
                            ? 'Glavni račun s podračuni dovoljuje le urejanje imena, lastnika, APY-ja in vrstnega reda.'
                            : 'Vnesite podatke računa ali podračuna.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submitAccount">
                <div class="grid gap-2">
                    <Label for="account-name">Ime</Label>
                    <Input id="account-name" v-model="accountForm.name" />
                    <InputError :message="accountForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="account-person">Oseba</Label>
                    <Select
                        :model-value="accountForm.person_id"
                        @update:model-value="updatePerson"
                    >
                        <SelectTrigger id="account-person">
                            <SelectValue placeholder="Izberite osebo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in personOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="accountForm.errors.person_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="account-apy">APY (%)</Label>
                    <Input
                        id="account-apy"
                        v-model="accountForm.apy"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                    />
                    <InputError :message="accountForm.errors.apy" />
                </div>

                <div class="grid gap-2">
                    <Label for="account-sort-order">Vrstni red</Label>
                    <Input
                        id="account-sort-order"
                        v-model="accountForm.sort_order"
                        type="number"
                        min="0"
                        step="1"
                    />
                    <InputError :message="accountForm.errors.sort_order" />
                </div>

                <template v-if="!editingAccount?.has_children">
                    <div class="grid gap-2">
                        <Label for="account-amount">Začetni znesek</Label>
                        <Input
                            id="account-amount"
                            v-model="accountForm.amount"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="accountForm.errors.amount" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="account-parent">Nadrejeni račun</Label>
                        <Select
                            :model-value="accountForm.parent_id"
                            @update:model-value="updateParent"
                        >
                            <SelectTrigger id="account-parent">
                                <SelectValue
                                    placeholder="Brez nadrejenega računa"
                                />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="NO_PARENT_VALUE"
                                    >Brez nadrejenega računa</SelectItem
                                >
                                <SelectItem
                                    v-for="option in parentOptions"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="accountForm.errors.parent_id" />
                    </div>
                </template>

                <p
                    v-else
                    class="rounded-lg border border-dashed border-muted-foreground/40 bg-muted/30 px-3 py-2 text-sm text-muted-foreground"
                >
                    Znesek glavnega računa se samodejno izračuna iz podračunov
                    in ga ni mogoče ročno spreminjati.
                </p>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showAccountModal = false"
                        >Prekliči</Button
                    >
                    <Button type="submit" :disabled="accountForm.processing">
                        {{
                            editingAccount ? 'Shrani spremembe' : 'Dodaj račun'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showBalanceModal">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Prenos ali popravek zneska</DialogTitle>
                <DialogDescription>
                    Prilagodite stanje računa
                    {{ selectedBalanceAccount?.name }} ali prenesite znesek med
                    dvema leaf računoma.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submitBalance">
                <div class="grid gap-2">
                    <Label for="balance-operation">Vrsta spremembe</Label>
                    <Select
                        :model-value="balanceForm.operation"
                        @update:model-value="updateBalanceOperation"
                    >
                        <SelectTrigger id="balance-operation">
                            <SelectValue
                                placeholder="Izberite vrsto spremembe"
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="add">Dodaj</SelectItem>
                            <SelectItem value="subtract">Odštej</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="balanceForm.errors.operation" />
                </div>

                <div class="grid gap-2">
                    <Label for="balance-amount">Znesek</Label>
                    <Input
                        id="balance-amount"
                        v-model="balanceForm.amount"
                        type="number"
                        min="0.01"
                        step="0.01"
                    />
                    <InputError :message="balanceForm.errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="balance-related-account">
                        Drugi račun
                        <span class="text-muted-foreground">(neobvezno)</span>
                    </Label>
                    <Select
                        :model-value="balanceForm.related_account_id"
                        @update:model-value="updateRelatedAccount"
                    >
                        <SelectTrigger id="balance-related-account">
                            <SelectValue placeholder="Brez drugega računa" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NO_RELATED_ACCOUNT_VALUE">
                                Brez drugega računa
                            </SelectItem>
                            <SelectItem
                                v-for="option in relatedAccountOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p class="text-sm text-muted-foreground">
                        {{ balanceFlowHint }}
                    </p>
                    <InputError
                        :message="balanceForm.errors.related_account_id"
                    />
                </div>

                <div
                    v-if="selectedBalanceAccount"
                    class="rounded-lg bg-muted/40 p-4 text-sm"
                >
                    <p>
                        Izbrani račun:
                        {{ selectedBalanceAccount.name }}
                        ({{ formatNumber(selectedBalanceAccount.amount) }} €)
                    </p>
                    <p v-if="selectedRelatedAccount">
                        Drugi račun:
                        {{ selectedRelatedAccount.label }}
                        ({{ formatNumber(selectedRelatedAccount.amount) }} €)
                    </p>
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
                        Shrani spremembo
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showInterestModal">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>Dodaj obresti</DialogTitle>
                <DialogDescription>
                    <template v-if="selectedInterestAccount?.has_children">
                        Obresti bodo razdeljene med podračune računa
                        {{ selectedInterestAccount.name }}.
                    </template>
                    <template v-else>
                        Obresti bodo prištete neposredno na račun
                        {{ selectedInterestAccount?.name }}.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submitInterest">
                <div class="grid gap-2">
                    <Label for="interest-amount">Znesek obresti</Label>
                    <Input
                        id="interest-amount"
                        v-model="interestForm.amount"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="interestForm.errors.amount" />
                </div>

                <div
                    v-if="
                        selectedInterestAccount &&
                        !selectedInterestAccount.has_children
                    "
                    class="rounded-lg bg-muted/40 p-4 text-sm"
                >
                    <p>
                        Trenutni znesek:
                        {{ formatNumber(selectedInterestAccount.amount) }} €
                    </p>
                    <p>
                        Nov znesek:
                        {{
                            formatNumber(
                                projectedLeafAmount ??
                                    selectedInterestAccount.amount,
                            )
                        }}
                        €
                    </p>
                </div>

                <div
                    v-else-if="interestPreviewRows.length > 0"
                    class="overflow-x-auto"
                >
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Podračun</TableHead>
                                <TableHead numeric class="text-right"
                                    >Znesek</TableHead
                                >
                                <TableHead numeric class="text-right"
                                    >Delež (%)</TableHead
                                >
                                <TableHead numeric class="text-right"
                                    >Obresti</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="row in interestPreviewRows"
                                :key="row.id"
                            >
                                <TableCell>{{ row.name }}</TableCell>
                                <TableCell numeric class="text-right"
                                    >{{ formatNumber(row.amount) }} €</TableCell
                                >
                                <TableCell numeric class="text-right"
                                    >{{
                                        formatNumber(row.percentage)
                                    }}
                                    %</TableCell
                                >
                                <TableCell numeric class="text-right"
                                    >{{ formatNumber(row.share) }} €</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showInterestModal = false"
                        >Prekliči</Button
                    >
                    <Button type="submit" :disabled="interestForm.processing"
                        >Dodaj obresti</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
