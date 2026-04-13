<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Bitcoin,
    ChartColumnIncreasing,
    ChartCandlestick,
    LayoutGrid,
    PiggyBank,
    Settings,
    Wallet,
} from '@lucide/vue';
import { computed } from 'vue';
import { index as cryptoBalanceIndex } from '@/actions/App/Http/Controllers/CryptoBalanceController';
import { index as cryptoDcaIndex } from '@/actions/App/Http/Controllers/CryptoDcaPurchaseController';
import { show as investmentProviderShow } from '@/actions/App/Http/Controllers/InvestmentProviderController';
import { index as investmentProviderSettingsIndex } from '@/actions/App/Http/Controllers/InvestmentProviderSettingsController';
import { index as investmentSymbolIndex } from '@/actions/App/Http/Controllers/InvestmentSymbolController';
import { index as placeIndex } from '@/actions/App/Http/Controllers/PaycheckController';
import { index as peopleIndex } from '@/actions/App/Http/Controllers/PersonController';
import { index as savingsIndex } from '@/actions/App/Http/Controllers/SavingsAccountController';
import {
    index as statisticsIndex,
    monthlySummary as statisticsMonthlySummary,
    paycheckGrowth as statisticsPaycheckGrowth,
    yearlyInvested as statisticsYearlyInvested,
} from '@/actions/App/Http/Controllers/StatisticsController';
import { index as nastavitveIndex } from '@/actions/App/Http/Controllers/TaxSettingController';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    const people = page.props.activePeople;
    const firstPerson = people[0];
    const investmentProviders = page.props.investmentProviders;
    const firstInvestmentProvider = investmentProviders[0];

    return [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Varčevanje',
            href: savingsIndex.url(),
            icon: PiggyBank,
        },
        {
            title: 'Plače',
            href: firstPerson
                ? placeIndex.url(firstPerson.slug)
                : peopleIndex.url(),
            icon: Wallet,
            children: people.map((person) => ({
                title: person.name,
                href: placeIndex.url(person.slug),
            })),
        },
        {
            title: 'Investicije',
            href: firstInvestmentProvider
                ? investmentProviderShow.url(firstInvestmentProvider.slug)
                : investmentSymbolIndex.url(),
            icon: ChartCandlestick,
            children: investmentProviders.map((provider) => ({
                title: provider.name,
                href: investmentProviderShow.url(provider.slug),
            })),
        },
        {
            title: 'Kripto',
            href: cryptoBalanceIndex.url(),
            icon: Bitcoin,
            children: [
                {
                    title: 'Stanja',
                    href: cryptoBalanceIndex.url(),
                },
                {
                    title: 'DCA transakcije',
                    href: cryptoDcaIndex.url(),
                },
            ],
        },
        {
            title: 'Statistika',
            href: statisticsIndex.url(),
            icon: ChartColumnIncreasing,
            children: [
                {
                    title: 'Mesečni povzetek',
                    href: statisticsMonthlySummary.url(),
                },
                {
                    title: 'Letni vložki',
                    href: statisticsYearlyInvested.url(),
                },
                {
                    title: 'Rast plač',
                    href: statisticsPaycheckGrowth.url(),
                },
            ],
        },
    ];
});

const footerNavItems: NavItem[] = [
    {
        title: 'Nastavitve',
        href: nastavitveIndex.url(),
        icon: Settings,
        children: [
            {
                title: 'Davčne nastavitve',
                href: nastavitveIndex.url(),
            },
            {
                title: 'Simboli',
                href: investmentSymbolIndex.url(),
            },
            {
                title: 'Ponudniki',
                href: investmentProviderSettingsIndex.url(),
            },
            {
                title: 'Osebe',
                href: peopleIndex.url(),
            },
        ],
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
