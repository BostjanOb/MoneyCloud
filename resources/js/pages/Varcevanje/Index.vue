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
    owner: string;
    owner_label: string;
    amount: string;
    apy: string;
    sort_order: number;
    annual_yield: string;
    monthly_yield: string;
    has_children: boolean;
    children: AccountNode[];
};

type OwnerOption = {
    value: string;
    label: string;
};

type Totals = {
    amount: string;
    annual_yield: string;
    monthly_yield: string;
};

type Props = {
    accounts: AccountNode[];
    ownerOptions: OwnerOption[];
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
const defaultOwner = props.ownerOptions[0]?.value ?? 'bostjan';

const showAccountModal = ref(false);
const showInterestModal = ref(false);
const editingAccount = ref<AccountNode | null>(null);
const selectedInterestAccount = ref<AccountNode | null>(null);

const accountForm = useForm({
    name: '',
    owner: defaultOwner,
    amount: '0',
    apy: '0',
    sort_order: '0',
    parent_id: NO_PARENT_VALUE,
});

const interestForm = useForm({
    amount: '',
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
        owner: defaultOwner,
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

function openEditAccount(account: AccountNode): void {
    editingAccount.value = account;
    accountForm.clearErrors();
    accountForm.name = account.name;
    accountForm.owner = account.owner;
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
                owner: data.owner,
                apy: data.apy,
                sort_order: Number(data.sort_order),
            };
        }

        return {
            name: data.name,
            owner: data.owner,
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

function updateOwner(value: AcceptableValue): void {
    if (typeof value === 'string') {
        accountForm.owner = value;
    }
}

function updateParent(value: AcceptableValue): void {
    if (typeof value === 'string') {
        accountForm.parent_id = value;
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
                            <TableHead>Lastnik</TableHead>
                            <TableHead numeric class="text-right"
                                >Znesek</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >APY</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Letni donos</TableHead
                            >
                            <TableHead numeric class="text-right"
                                >Mesečni donos</TableHead
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
                                row.isRoot ? row.account.owner_label : ''
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
                    <Label for="account-owner">Lastnik</Label>
                    <Select
                        :model-value="accountForm.owner"
                        @update:model-value="updateOwner"
                    >
                        <SelectTrigger id="account-owner">
                            <SelectValue placeholder="Izberite lastnika" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in ownerOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="accountForm.errors.owner" />
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
