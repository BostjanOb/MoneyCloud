<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
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
import {
    store as transferTemplateStore,
    update as transferTemplateUpdate,
    destroy as transferTemplateDestroy,
} from '@/actions/App/Http/Controllers/SavingsTransferTemplateController';
import { store as transferTemplateExecute } from '@/actions/App/Http/Controllers/SavingsTransferTemplateExecutionController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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

type TransferTemplateItem = {
    savings_account_id: number;
    label: string;
    amount: string;
};

type TransferTemplate = {
    id: number;
    name: string;
    source_account_id: number | null;
    source_label: string | null;
    sort_order: number;
    total: string;
    items: TransferTemplateItem[];
};

type TemplateFormItem = {
    savings_account_id: string;
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
    transferTemplates: TransferTemplate[];
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
const NO_SOURCE_ACCOUNT_VALUE = '__none__';
const defaultPerson = props.personOptions[0]?.value ?? '';

const showAccountModal = ref(false);
const showBalanceModal = ref(false);
const showInterestModal = ref(false);
const editingAccount = ref<AccountNode | null>(null);
const selectedBalanceAccount = ref<AccountNode | null>(null);
const selectedInterestAccount = ref<AccountNode | null>(null);
const showTemplateModal = ref(false);
const showExecuteModal = ref(false);
const editingTemplate = ref<TransferTemplate | null>(null);
const selectedExecuteTemplate = ref<TransferTemplate | null>(null);

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

const page = usePage();
const flash = computed(
    () =>
        (page.props.flash ?? {}) as {
            status?: string;
        },
);
const executingTemplateId = ref<number | null>(null);
const templateExecutionErrors = ref<Record<number, string | undefined>>({});

const templateForm = useForm<{
    name: string;
    source_account_id: string;
    items: TemplateFormItem[];
}>({
    name: '',
    source_account_id: NO_SOURCE_ACCOUNT_VALUE,
    items: [{ savings_account_id: '', amount: '' }],
});

const executeForm = useForm({
    source_account_id: '',
});

const rootAccounts = computed(() => props.accounts);

const isSubaccountForm = computed(
    () =>
        !editingAccount.value?.has_children &&
        accountForm.parent_id !== NO_PARENT_VALUE,
);

const inheritedApy = computed(
    () =>
        rootAccounts.value.find(
            (account) => String(account.id) === accountForm.parent_id,
        )?.apy ?? null,
);

const templateTotal = computed(() =>
    fromCents(
        templateForm.items.reduce((sum, item) => sum + toCents(item.amount), 0),
    ),
);

const executeSourceOptions = computed(() => {
    const targetIds = new Set(
        selectedExecuteTemplate.value?.items.map(
            (item) => item.savings_account_id,
        ) ?? [],
    );

    return props.leafAccountOptions.filter(
        (account) => !targetIds.has(account.id),
    );
});

const selectedExecuteSource = computed(
    () =>
        executeSourceOptions.value.find(
            (account) => String(account.id) === executeForm.source_account_id,
        ) ?? null,
);

const executePreviewRows = computed(() => {
    const template = selectedExecuteTemplate.value;

    if (template === null) {
        return [];
    }

    return template.items.map((item) => {
        const currentAmount =
            props.leafAccountOptions.find(
                (account) => account.id === item.savings_account_id,
            )?.amount ?? '0';

        return {
            id: item.savings_account_id,
            label: item.label,
            amount: item.amount,
            currentAmount,
            newAmount: fromCents(toCents(currentAmount) + toCents(item.amount)),
        };
    });
});

const executeSourceNewAmount = computed(() => {
    const source = selectedExecuteSource.value;
    const template = selectedExecuteTemplate.value;

    if (source === null || template === null) {
        return null;
    }

    return fromCents(toCents(source.amount) - toCents(template.total));
});

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

        const isSubaccount = data.parent_id !== NO_PARENT_VALUE;

        return {
            name: data.name,
            person_id: Number(data.person_id),
            amount: data.amount,
            ...(isSubaccount ? {} : { apy: data.apy }),
            sort_order: Number(data.sort_order),
            parent_id: isSubaccount ? Number(data.parent_id) : null,
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

function resetTemplateForm(): void {
    templateForm.defaults({
        name: '',
        source_account_id: NO_SOURCE_ACCOUNT_VALUE,
        items: [{ savings_account_id: '', amount: '' }],
    });
    templateForm.reset();
    templateForm.clearErrors();
}

function openCreateTemplate(): void {
    editingTemplate.value = null;
    resetTemplateForm();
    showTemplateModal.value = true;
}

function openEditTemplate(template: TransferTemplate): void {
    editingTemplate.value = template;
    templateForm.clearErrors();
    templateForm.name = template.name;
    templateForm.source_account_id =
        template.source_account_id === null
            ? NO_SOURCE_ACCOUNT_VALUE
            : String(template.source_account_id);
    templateForm.items = template.items.map((item) => ({
        savings_account_id: String(item.savings_account_id),
        amount: item.amount,
    }));
    showTemplateModal.value = true;
}

function addTemplateItem(): void {
    templateForm.items.push({ savings_account_id: '', amount: '' });
}

function removeTemplateItem(index: number): void {
    templateForm.items.splice(index, 1);
}

function updateTemplateItemAccount(
    index: number,
    value: AcceptableValue,
): void {
    const item = templateForm.items[index];

    if (item !== undefined && typeof value === 'string') {
        item.savings_account_id = value;
    }
}

function templateItemError(
    index: number,
    field: keyof TemplateFormItem,
): string | undefined {
    return (templateForm.errors as Record<string, string | undefined>)[
        `items.${index}.${field}`
    ];
}

const templateSourceOptions = computed(() => {
    const targetIds = new Set(
        templateForm.items.map((item) => item.savings_account_id),
    );

    return props.leafAccountOptions.filter(
        (account) => !targetIds.has(String(account.id)),
    );
});

function updateTemplateSource(value: AcceptableValue): void {
    if (typeof value === 'string') {
        templateForm.source_account_id = value;
    }
}

function templateAccountOptions(index: number): LeafAccountOption[] {
    const selectedElsewhere = new Set([
        ...templateForm.items
            .filter((_, itemIndex) => itemIndex !== index)
            .map((item) => item.savings_account_id),
        templateForm.source_account_id,
    ]);

    return props.leafAccountOptions.filter(
        (account) => !selectedElsewhere.has(String(account.id)),
    );
}

function submitTemplate(): void {
    templateForm.transform((data) => ({
        name: data.name,
        source_account_id:
            data.source_account_id === NO_SOURCE_ACCOUNT_VALUE
                ? null
                : Number(data.source_account_id),
        items: data.items.map((item) => ({
            savings_account_id:
                item.savings_account_id === ''
                    ? null
                    : Number(item.savings_account_id),
            amount: item.amount,
        })),
    }));

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            showTemplateModal.value = false;
            editingTemplate.value = null;
            resetTemplateForm();
        },
    };

    if (editingTemplate.value) {
        templateForm.put(
            transferTemplateUpdate.url(editingTemplate.value.id),
            options,
        );

        return;
    }

    templateForm.post(transferTemplateStore.url(), options);
}

function deleteTemplate(template: TransferTemplate): void {
    if (
        !confirm(
            `Ste prepričani, da želite izbrisati predlogo ${template.name}?`,
        )
    ) {
        return;
    }

    router.delete(transferTemplateDestroy.url(template.id), {
        preserveScroll: true,
    });
}

function runTemplate(template: TransferTemplate): void {
    if (template.source_account_id === null) {
        openExecuteTemplate(template);

        return;
    }

    executingTemplateId.value = template.id;
    templateExecutionErrors.value[template.id] = undefined;

    router.post(
        transferTemplateExecute.url(template.id),
        {},
        {
            preserveScroll: true,
            onError: (errors) => {
                templateExecutionErrors.value[template.id] =
                    errors.source_account_id;
            },
            onFinish: () => {
                executingTemplateId.value = null;
            },
        },
    );
}

function openExecuteTemplate(template: TransferTemplate): void {
    selectedExecuteTemplate.value = template;
    executeForm.defaults({
        source_account_id:
            template.source_account_id === null
                ? ''
                : String(template.source_account_id),
    });
    executeForm.reset();
    executeForm.clearErrors();
    showExecuteModal.value = true;
}

function updateExecuteSource(value: AcceptableValue): void {
    if (typeof value === 'string') {
        executeForm.source_account_id = value;
    }
}

function submitExecute(): void {
    const template = selectedExecuteTemplate.value;

    if (template === null) {
        return;
    }

    executeForm.transform((data) => ({
        source_account_id:
            data.source_account_id === ''
                ? null
                : Number(data.source_account_id),
    }));

    executeForm.post(transferTemplateExecute.url(template.id), {
        preserveScroll: true,
        onSuccess: () => {
            showExecuteModal.value = false;
            selectedExecuteTemplate.value = null;
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
        <Alert v-if="flash.status">
            <AlertTitle>Prenos izveden</AlertTitle>
            <AlertDescription>{{ flash.status }}</AlertDescription>
        </Alert>

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

        <Card>
            <CardHeader
                class="flex flex-row items-center justify-between gap-4"
            >
                <CardTitle>Predloge prenosov</CardTitle>
                <Button size="sm" variant="outline" @click="openCreateTemplate">
                    Dodaj predlogo
                </Button>
            </CardHeader>
            <CardContent class="overflow-x-auto">
                <Table v-if="transferTemplates.length > 0">
                    <TableHeader>
                        <TableRow>
                            <TableHead>Ime</TableHead>
                            <TableHead>Iz računa</TableHead>
                            <TableHead>Ciljni računi</TableHead>
                            <TableHead numeric class="text-right"
                                >Skupaj</TableHead
                            >
                            <TableHead class="text-right">Akcije</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="template in transferTemplates"
                            :key="template.id"
                        >
                            <TableCell class="font-medium">{{
                                template.name
                            }}</TableCell>
                            <TableCell class="text-sm">
                                <span v-if="template.source_label">{{
                                    template.source_label
                                }}</span>
                                <span v-else class="text-muted-foreground"
                                    >Izbere se ob izvedbi</span
                                >
                            </TableCell>
                            <TableCell>
                                <div class="flex flex-col gap-0.5 text-sm">
                                    <span
                                        v-for="item in template.items"
                                        :key="item.savings_account_id"
                                    >
                                        {{ item.label }}:
                                        {{ formatNumber(item.amount) }} €
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell numeric class="text-right"
                                >{{ formatNumber(template.total) }} €</TableCell
                            >
                            <TableCell class="text-right">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        size="sm"
                                        :disabled="
                                            template.items.length === 0 ||
                                            executingTemplateId !== null
                                        "
                                        @click="runTemplate(template)"
                                        >Izvedi</Button
                                    >
                                    <Button
                                        v-if="
                                            template.source_account_id !== null
                                        "
                                        variant="ghost"
                                        size="sm"
                                        :disabled="template.items.length === 0"
                                        @click="openExecuteTemplate(template)"
                                        >Drug vir</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        @click="openEditTemplate(template)"
                                        >Uredi</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="text-destructive"
                                        @click="deleteTemplate(template)"
                                        >Briši</Button
                                    >
                                </div>
                                <InputError
                                    class="mt-1"
                                    :message="
                                        templateExecutionErrors[template.id]
                                    "
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
                <p
                    v-else
                    class="py-8 text-center text-sm text-muted-foreground"
                >
                    Ni še predlog. Predloga omogoča prenos na več računov z enim
                    klikom (npr. otroški dodatki).
                </p>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="showTemplateModal">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{
                    editingTemplate ? 'Uredi predlogo' : 'Dodaj predlogo'
                }}</DialogTitle>
                <DialogDescription>
                    Določite izvorni račun, ciljne račune in zneske. Če
                    izvornega računa ne določite, ga izberete ob vsaki izvedbi.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submitTemplate">
                <div class="grid gap-2">
                    <Label for="template-name">Ime</Label>
                    <Input
                        id="template-name"
                        v-model="templateForm.name"
                        placeholder="Otroški dodatki"
                    />
                    <InputError :message="templateForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="template-source">Iz računa</Label>
                    <Select
                        :model-value="templateForm.source_account_id"
                        @update:model-value="updateTemplateSource"
                    >
                        <SelectTrigger id="template-source">
                            <SelectValue placeholder="Izberi ob izvedbi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem :value="NO_SOURCE_ACCOUNT_VALUE"
                                >Izberi ob izvedbi</SelectItem
                            >
                            <SelectItem
                                v-for="option in templateSourceOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="templateForm.errors.source_account_id"
                    />
                </div>

                <div class="grid gap-3">
                    <Label>Ciljni računi</Label>
                    <div
                        v-for="(item, index) in templateForm.items"
                        :key="index"
                        class="grid gap-1"
                    >
                        <div class="flex items-start gap-2">
                            <Select
                                :model-value="item.savings_account_id"
                                @update:model-value="
                                    (value) =>
                                        updateTemplateItemAccount(index, value)
                                "
                            >
                                <SelectTrigger class="flex-1">
                                    <SelectValue placeholder="Izberite račun" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in templateAccountOptions(
                                            index,
                                        )"
                                        :key="option.id"
                                        :value="String(option.id)"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                v-model="item.amount"
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="Znesek"
                                class="w-32"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                class="text-destructive"
                                :disabled="templateForm.items.length === 1"
                                @click="removeTemplateItem(index)"
                                >Odstrani</Button
                            >
                        </div>
                        <InputError
                            :message="
                                templateItemError(index, 'savings_account_id')
                            "
                        />
                        <InputError
                            :message="templateItemError(index, 'amount')"
                        />
                    </div>
                    <InputError :message="templateForm.errors.items" />
                    <div class="flex items-center justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addTemplateItem"
                            >Dodaj račun</Button
                        >
                        <span class="text-sm text-muted-foreground">
                            Skupaj: {{ formatNumber(templateTotal) }} €
                        </span>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showTemplateModal = false"
                        >Prekliči</Button
                    >
                    <Button type="submit" :disabled="templateForm.processing">
                        {{
                            editingTemplate
                                ? 'Shrani spremembe'
                                : 'Dodaj predlogo'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showExecuteModal">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>Izvedi predlogo</DialogTitle>
                <DialogDescription>
                    Skupaj
                    {{ formatNumber(selectedExecuteTemplate?.total ?? 0) }} € bo
                    prenesenih iz izbranega računa na ciljne račune predloge
                    {{ selectedExecuteTemplate?.name }}.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="submitExecute">
                <div class="grid gap-2">
                    <Label for="execute-source">Izvorni račun</Label>
                    <Select
                        :model-value="executeForm.source_account_id"
                        @update:model-value="updateExecuteSource"
                    >
                        <SelectTrigger id="execute-source">
                            <SelectValue placeholder="Izberite izvorni račun" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in executeSourceOptions"
                                :key="option.id"
                                :value="String(option.id)"
                            >
                                {{ option.label }} ({{
                                    formatNumber(option.amount)
                                }}
                                €)
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="executeForm.errors.source_account_id"
                    />
                </div>

                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Račun</TableHead>
                                <TableHead numeric class="text-right"
                                    >Sprememba</TableHead
                                >
                                <TableHead numeric class="text-right"
                                    >Trenutno</TableHead
                                >
                                <TableHead numeric class="text-right"
                                    >Novo</TableHead
                                >
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="selectedExecuteSource">
                                <TableCell>{{
                                    selectedExecuteSource.label
                                }}</TableCell>
                                <TableCell
                                    numeric
                                    class="text-right text-destructive"
                                    >−{{
                                        formatNumber(
                                            selectedExecuteTemplate?.total ?? 0,
                                        )
                                    }}
                                    €</TableCell
                                >
                                <TableCell numeric class="text-right"
                                    >{{
                                        formatNumber(
                                            selectedExecuteSource.amount,
                                        )
                                    }}
                                    €</TableCell
                                >
                                <TableCell
                                    numeric
                                    :class="
                                        cn(
                                            'text-right',
                                            Number(executeSourceNewAmount) < 0
                                                ? 'text-destructive'
                                                : '',
                                        )
                                    "
                                    >{{
                                        formatNumber(
                                            executeSourceNewAmount ?? 0,
                                        )
                                    }}
                                    €</TableCell
                                >
                            </TableRow>
                            <TableRow
                                v-for="row in executePreviewRows"
                                :key="row.id"
                            >
                                <TableCell>{{ row.label }}</TableCell>
                                <TableCell numeric class="text-right"
                                    >+{{
                                        formatNumber(row.amount)
                                    }}
                                    €</TableCell
                                >
                                <TableCell numeric class="text-right"
                                    >{{
                                        formatNumber(row.currentAmount)
                                    }}
                                    €</TableCell
                                >
                                <TableCell numeric class="text-right"
                                    >{{
                                        formatNumber(row.newAmount)
                                    }}
                                    €</TableCell
                                >
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showExecuteModal = false"
                        >Prekliči</Button
                    >
                    <Button
                        type="submit"
                        :disabled="
                            executeForm.processing ||
                            executeForm.source_account_id === ''
                        "
                        >Izvedi prenos</Button
                    >
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

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
                        v-if="isSubaccountForm"
                        id="account-apy"
                        :model-value="inheritedApy ?? ''"
                        type="number"
                        disabled
                    />
                    <Input
                        v-else
                        id="account-apy"
                        v-model="accountForm.apy"
                        type="number"
                        min="0"
                        max="100"
                        step="0.01"
                    />
                    <p
                        v-if="isSubaccountForm"
                        class="text-sm text-muted-foreground"
                    >
                        APY se podeduje od nadrejenega računa.
                    </p>
                    <p
                        v-else-if="editingAccount?.has_children"
                        class="text-sm text-muted-foreground"
                    >
                        Sprememba APY-ja se prenese na vse podračune.
                    </p>
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
