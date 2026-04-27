<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm, usePage } from '@inertiajs/vue3';
import type { AcceptableValue } from 'reka-ui';
import { computed, watchEffect } from 'vue';
import { show as providerShow } from '@/actions/App/Http/Controllers/InvestmentProviderController';
import {
    index as providerIndex,
    store as providerStore,
    update as providerUpdate,
} from '@/actions/App/Http/Controllers/InvestmentProviderSettingsController';
import { index as symbolIndex } from '@/actions/App/Http/Controllers/InvestmentSymbolController';
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
import { formatSlovenianNumber } from '@/lib/utils';

type ProviderFormData = {
    id: number;
    name: string;
    slug: string;
    sort_order: number;
    linked_savings_account_id: number | null;
    requires_linked_savings_account: boolean;
    supported_symbol_types: string[];
    balance_sync_provider: string | null;
};

type TypeOption = {
    value: string;
    label: string;
};

type SavingsAccountOption = {
    id: number;
    label: string;
    amount: string;
};

type SyncProviderOption = {
    value: string;
    label: string;
};

type Props = {
    provider: ProviderFormData | null;
    typeOptions: TypeOption[];
    syncProviderOptions: SyncProviderOption[];
    savingsAccountOptions: SavingsAccountOption[];
};

const NO_SAVINGS_ACCOUNT = '__none__';
const NO_SYNC_PROVIDER = '__none__';

const props = defineProps<Props>();

const page = usePage();
const investmentsHref = computed(() => {
    const firstProvider = page.props.investmentProviders[0];

    return firstProvider
        ? providerShow.url(firstProvider.slug)
        : symbolIndex.url();
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: 'Investicije',
                href: investmentsHref.value,
            },
            {
                title: 'Ponudniki',
                href: providerIndex.url(),
            },
        ],
    });
});

const isEditing = computed(() => props.provider !== null);
const supportsCrypto = computed(() =>
    form.supported_symbol_types.includes('crypto'),
);

const form = useForm({
    name: props.provider?.name ?? '',
    slug: props.provider?.slug ?? '',
    sort_order: String(props.provider?.sort_order ?? 0),
    requires_linked_savings_account:
        props.provider?.requires_linked_savings_account ?? false,
    linked_savings_account_id: props.provider?.linked_savings_account_id
        ? String(props.provider.linked_savings_account_id)
        : NO_SAVINGS_ACCOUNT,
    supported_symbol_types: props.provider?.supported_symbol_types ?? [],
    balance_sync_provider:
        props.provider?.balance_sync_provider ?? NO_SYNC_PROVIDER,
});

const selectedLinkedSavingsAccount = computed(() =>
    props.savingsAccountOptions.find(
        (account) => String(account.id) === form.linked_savings_account_id,
    ),
);

function updateLinkedSavingsAccount(value: AcceptableValue): void {
    if (typeof value === 'string') {
        form.linked_savings_account_id = value;
    }
}

function updateBalanceSyncProvider(value: AcceptableValue): void {
    if (typeof value === 'string') {
        form.balance_sync_provider = value;
    }
}

function updateRequiresLinkedSavingsAccount(
    value: boolean | 'indeterminate',
): void {
    form.requires_linked_savings_account = value === true;

    if (!form.requires_linked_savings_account) {
        form.linked_savings_account_id = NO_SAVINGS_ACCOUNT;
        form.clearErrors('linked_savings_account_id');
    }
}

function toggleSupportedType(
    type: string,
    value: boolean | 'indeterminate',
): void {
    if (value === true) {
        form.supported_symbol_types = props.typeOptions
            .map((option) => option.value)
            .filter(
                (option) =>
                    option === type ||
                    form.supported_symbol_types.includes(option),
            );

        return;
    }

    form.supported_symbol_types = form.supported_symbol_types.filter(
        (supportedType) => supportedType !== type,
    );

    if (type === 'crypto') {
        form.balance_sync_provider = NO_SYNC_PROVIDER;
        form.clearErrors('balance_sync_provider');
    }
}

function formatMoney(value: string): string {
    return `${formatSlovenianNumber(value)} €`;
}

function submitProvider(): void {
    form.transform((data) => ({
        ...data,
        sort_order: Number(data.sort_order),
        linked_savings_account_id:
            data.requires_linked_savings_account &&
            data.linked_savings_account_id !== NO_SAVINGS_ACCOUNT
                ? Number(data.linked_savings_account_id)
                : null,
        supported_symbol_types: data.supported_symbol_types,
        balance_sync_provider:
            data.supported_symbol_types.includes('crypto') &&
            data.balance_sync_provider !== NO_SYNC_PROVIDER
                ? data.balance_sync_provider
                : null,
    }));

    if (props.provider) {
        form.put(providerUpdate.url(props.provider.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(providerStore.url(), {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="isEditing ? 'Uredi ponudnika' : 'Nov ponudnik'" />

    <div class="flex flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-4">
            <Heading
                :title="isEditing ? 'Uredi ponudnika' : 'Nov ponudnik'"
                :description="
                    isEditing
                        ? 'Posodobite ime, tipe simbolov in pravila povezave z računom.'
                        : 'Dodajte ponudnika za nove nakupe in povezave z računi.'
                "
            />
            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="providerIndex.url()">Nazaj na ponudnike</Link>
                </Button>
                <Button @click="submitProvider" :disabled="form.processing">
                    {{ isEditing ? 'Shrani spremembe' : 'Shrani ponudnika' }}
                </Button>
            </div>
        </div>

        <form
            class="grid gap-5 lg:grid-cols-[1.2fr_0.8fr]"
            @submit.prevent="submitProvider"
        >
            <Card>
                <CardHeader>
                    <CardTitle>Osnovni podatki</CardTitle>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="space-y-1.5">
                        <Label for="provider-name">Ime</Label>
                        <Input id="provider-name" v-model="form.name" />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="provider-slug">Slug</Label>
                        <Input
                            id="provider-slug"
                            v-model="form.slug"
                            placeholder="Samodejno iz imena"
                        />
                        <InputError :message="form.errors.slug" />
                    </div>

                    <div class="space-y-1.5">
                        <Label for="provider-sort-order">Vrstni red</Label>
                        <Input
                            id="provider-sort-order"
                            v-model="form.sort_order"
                            type="number"
                            min="0"
                            step="1"
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>
                </CardContent>
            </Card>

            <div class="grid gap-5">
                <Card>
                    <CardHeader>
                        <CardTitle>Podprti tipi simbolov</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-3">
                        <Label
                            v-for="option in typeOptions"
                            :key="option.value"
                            :for="`provider-type-${option.value}`"
                            class="flex items-center gap-3 rounded-lg border p-3"
                        >
                            <Checkbox
                                :id="`provider-type-${option.value}`"
                                :model-value="
                                    form.supported_symbol_types.includes(
                                        option.value,
                                    )
                                "
                                @update:model-value="
                                    (value) =>
                                        toggleSupportedType(option.value, value)
                                "
                            />
                            <span>{{ option.label }}</span>
                        </Label>
                        <InputError
                            :message="form.errors.supported_symbol_types"
                        />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Povezava z računom</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <Label
                            for="provider-requires-linked-account"
                            class="flex items-center gap-3 rounded-lg border p-3"
                        >
                            <Checkbox
                                id="provider-requires-linked-account"
                                :model-value="
                                    form.requires_linked_savings_account
                                "
                                @update:model-value="
                                    updateRequiresLinkedSavingsAccount
                                "
                            />
                            <span>Ob nakupu je potreben povezan račun</span>
                        </Label>
                        <InputError
                            :message="
                                form.errors.requires_linked_savings_account
                            "
                        />

                        <div
                            v-if="form.requires_linked_savings_account"
                            class="grid gap-2"
                        >
                            <Label for="provider-linked-account">
                                Varčevalni račun
                            </Label>
                            <Select
                                :model-value="form.linked_savings_account_id"
                                @update:model-value="updateLinkedSavingsAccount"
                            >
                                <SelectTrigger id="provider-linked-account">
                                    <SelectValue placeholder="Izberite račun" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NO_SAVINGS_ACCOUNT">
                                        Brez povezave
                                    </SelectItem>
                                    <SelectItem
                                        v-for="account in savingsAccountOptions"
                                        :key="account.id"
                                        :value="String(account.id)"
                                    >
                                        {{ account.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p
                                v-if="selectedLinkedSavingsAccount"
                                class="text-sm text-muted-foreground"
                            >
                                Trenutno stanje:
                                {{
                                    formatMoney(
                                        selectedLinkedSavingsAccount.amount,
                                    )
                                }}
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                Izberite leaf račun brez podračunov.
                            </p>
                            <InputError
                                :message="form.errors.linked_savings_account_id"
                            />
                        </div>
                    </CardContent>
                </Card>

                <Card v-if="supportsCrypto">
                    <CardHeader>
                        <CardTitle>Sinhronizacija stanj</CardTitle>
                    </CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="provider-balance-sync-provider">
                                Vir sinhronizacije
                            </Label>
                            <Select
                                :model-value="form.balance_sync_provider"
                                @update:model-value="
                                    updateBalanceSyncProvider
                                "
                            >
                                <SelectTrigger
                                    id="provider-balance-sync-provider"
                                >
                                    <SelectValue
                                        placeholder="Brez sinhronizacije"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem :value="NO_SYNC_PROVIDER">
                                        Brez sinhronizacije
                                    </SelectItem>
                                    <SelectItem
                                        v-for="option in syncProviderOptions"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <p class="text-sm text-muted-foreground">
                                Sinhronizacija bo posodabljala samo obstoječa
                                kripto stanja te platforme.
                            </p>
                            <InputError
                                :message="form.errors.balance_sync_provider"
                            />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </form>
    </div>
</template>
