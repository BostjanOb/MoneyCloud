<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { BookOpen, FolderGit2, LayoutGrid, Settings, Wallet } from 'lucide-vue-next';
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
import { index as placeIndex } from '@/actions/App/Http/Controllers/PaycheckController';
import { index as nastavitveIndex } from '@/actions/App/Http/Controllers/TaxSettingController';
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
];

const footerNavItems: NavItem[] = [
    {
        title: 'Davčne nastavitve',
        href: nastavitveIndex.url(),
        icon: FolderGit2,
    }
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
            <!-- <NavFooter :items="footerNavItems" /> -->
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
