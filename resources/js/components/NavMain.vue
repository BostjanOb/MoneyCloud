<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import { reactive, watch } from 'vue';
import { Collapsible, CollapsibleContent } from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
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

const props = defineProps<{
    items: NavItem[];
}>();

const { currentUrl, isCurrentUrl } = useCurrentUrl();
const { isMobile, setOpen, state } = useSidebar();

function isGroupActive(item: NavItem): boolean {
    return isSidebarNavGroupActive(item, isCurrentUrl);
}

const openItems = reactive<Record<string, boolean>>({});

watch(
    currentUrl,
    () => {
        props.items.forEach((item) => {
            if (item.children?.length && isGroupActive(item)) {
                openItems[item.title] = true;
            }
        });
    },
    { immediate: true },
);

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
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <!-- Items with children: collapsible -->
                <Collapsible
                    v-if="item.children?.length"
                    as-child
                    :open="isGroupOpen(item)"
                >
                    <SidebarMenuItem>
                        <SidebarMenuButton
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
                                        :is-active="isCurrentUrl(child.href)"
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

                <!-- Items without children: simple link -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="isCurrentUrl(item.href)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
