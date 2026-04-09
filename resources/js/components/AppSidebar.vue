<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChartCandlestick, LayoutGrid, PiggyBank, Wallet } from 'lucide-vue-next';
import { show as investmentProviderShow } from '@/actions/App/Http/Controllers/InvestmentProviderController';
import { index as investmentSymbolIndex } from '@/actions/App/Http/Controllers/InvestmentSymbolController';
import { index as placeIndex } from '@/actions/App/Http/Controllers/PaycheckController';
import { index as savingsIndex } from '@/actions/App/Http/Controllers/SavingsAccountController';
import { index as nastavitveIndex } from '@/actions/App/Http/Controllers/TaxSettingController';
import AppLogo from '@/components/AppLogo.vue';
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
            {
                title: 'Nastavitve',
                href: nastavitveIndex.url(),
            },
        ],
    },
    {
        title: 'Varčevanje',
        href: savingsIndex.url(),
        icon: PiggyBank,
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
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
