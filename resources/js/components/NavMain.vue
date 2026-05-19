<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight } from 'lucide-vue-next';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenuAction,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentOrParentUrl, isCurrentUrl } = useCurrentUrl();

const isItemActive = (item: NavItem): boolean =>
    item.items?.some((child) => isCurrentOrParentUrl(child.href)) ?? false;
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <template
                v-for="(item, index) in items"
                :key="`${item.title}-${index}`"
            >
                <SidebarSeparator v-if="item.dividerBefore" class="my-2" />

                <Collapsible
                    v-if="item.items?.length"
                    as-child
                    class="group/collapsible"
                    :default-open="isItemActive(item)"
                >
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            as-child
                            :is-active="isItemActive(item)"
                            :tooltip="item.title"
                        >
                            <Link :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                            </Link>
                        </SidebarMenuButton>

                        <CollapsibleTrigger as-child>
                            <SidebarMenuAction show-on-hover>
                                <ChevronRight
                                    class="transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90"
                                />
                                <span class="sr-only"
                                    >Toggle {{ item.title }}</span
                                >
                            </SidebarMenuAction>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.items"
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
