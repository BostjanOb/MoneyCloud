<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ChartCandlestick,
    LayoutGrid,
    PiggyBank,
    Settings,
    Wallet,
} from 'lucide-vue-next';
import { show as investmentProviderShow } from '@/actions/App/Http/Controllers/InvestmentProviderController';
import { index as investmentSymbolIndex } from '@/actions/App/Http/Controllers/InvestmentSymbolController';
import { index as placeIndex } from '@/actions/App/Http/Controllers/PaycheckController';
import { index as savingsIndex } from '@/actions/App/Http/Controllers/SavingsAccountController';
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

const mainNavItems: NavItem[] = [
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
        href: placeIndex.url('bostjan'),
        icon: Wallet,
        children: [
            {
                title: 'Boštjan',
                href: placeIndex.url('bostjan'),
            },
            {
                title: 'Jasna',
                href: placeIndex.url('jasna'),
            },
        ],
    },
    {
        title: 'Investicije',
        href: investmentProviderShow.url('ibkr'),
        icon: ChartCandlestick,
        children: [
            {
                title: 'IBKR',
                href: investmentProviderShow.url('ibkr'),
            },
            {
                title: 'Ilirika',
                href: investmentProviderShow.url('ilirika'),
            },
        ],
    },
];

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
