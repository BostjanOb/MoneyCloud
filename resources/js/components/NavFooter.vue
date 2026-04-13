<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from '@lucide/vue';
import { reactive } from 'vue';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { isSidebarNavGroupActive } from '@/lib/sidebarNavigation';
import { resolveSidebarSubmenuToggle } from '@/lib/sidebarSubmenu';
import type { NavItem } from '@/types';

type Props = {
    items: NavItem[];
    class?: string;
};

defineProps<Props>();

const { isCurrentUrl } = useCurrentUrl();
const { isMobile, setOpen, state } = useSidebar();
const openItems = reactive<Record<string, boolean>>({});

function isGroupActive(item: NavItem): boolean {
    return isSidebarNavGroupActive(item, isCurrentUrl);
}

function isGroupOpen(item: NavItem): boolean {
    return openItems[item.title] ?? false;
}

function toggleGroup(item: NavItem): void {
    const { nextSubmenuOpen, shouldExpandSidebar } =
        resolveSidebarSubmenuToggle({
            currentSubmenuOpen: isGroupOpen(item),
            isMobile: isMobile.value,
            sidebarState: state.value,
        });

    openItems[item.title] = nextSubmenuOpen;

    if (shouldExpandSidebar) {
        setOpen(true);
    }
}
</script>

<template>
    <SidebarGroup
        :class="`group-data-[collapsible=icon]:p-0 ${$props.class || ''}`"
    >
        <SidebarGroupContent>
            <SidebarMenu>
                <template v-for="item in items" :key="item.title">
                    <Collapsible
                        v-if="item.children?.length"
                        as-child
                        :open="isGroupOpen(item)"
                    >
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                                :is-active="isGroupActive(item)"
                                :tooltip="item.title"
                                aria-haspopup="true"
                                :aria-expanded="isGroupOpen(item)"
                                type="button"
                                @click="toggleGroup(item)"
                            >
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronRight
                                    class="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                            </SidebarMenuButton>
                            <CollapsibleContent>
                                <SidebarMenuSub>
                                    <SidebarMenuSubItem
                                        v-for="child in item.children"
                                        :key="child.title"
                                    >
                                        <SidebarMenuSubButton
                                            as-child
                                            :is-active="
                                                isCurrentUrl(child.href)
                                            "
                                        >
                                            <Link :href="child.href">
                                                <span>{{ child.title }}</span>
                                            </Link>
                                        </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                </SidebarMenuSub>
                            </CollapsibleContent>
                        </SidebarMenuItem>
                    </Collapsible>

                    <SidebarMenuItem v-else>
                        <SidebarMenuButton
                            class="text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100"
                            as-child
                            :is-active="isCurrentUrl(item.href)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" v-if="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </template>
            </SidebarMenu>
        </SidebarGroupContent>
    </SidebarGroup>
</template>
