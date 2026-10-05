<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShieldCheck, UserRound } from 'lucide-vue-next';
import { computed } from 'vue';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';

const props = defineProps<{
    isAdminPanel: boolean;
}>();

const panels = computed(() => [
    {
        key: 'user',
        title: 'User',
        href: dashboard(),
        icon: UserRound,
        active: !props.isAdminPanel,
    },
    {
        key: 'admin',
        title: 'Admin',
        href: adminDashboard(),
        icon: ShieldCheck,
        active: props.isAdminPanel,
    },
]);

const otherPanel = computed(() => panels.value.find((panel) => !panel.active));
</script>

<template>
    <div
        class="grid grid-cols-2 gap-1 rounded-lg bg-sidebar-accent p-1 group-data-[collapsible=icon]:hidden"
    >
        <Link
            v-for="panel in panels"
            :key="panel.key"
            :href="panel.href"
            :class="
                cn(
                    'flex items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground',
                    panel.active && 'bg-background text-foreground shadow-xs',
                )
            "
        >
            <component :is="panel.icon" class="size-3.5" />
            {{ panel.title }}
        </Link>
    </div>

    <SidebarMenu
        v-if="otherPanel"
        class="hidden group-data-[collapsible=icon]:flex"
    >
        <SidebarMenuItem>
            <SidebarMenuButton
                as-child
                :tooltip="`Switch to ${otherPanel.title} Panel`"
            >
                <Link :href="otherPanel.href">
                    <component :is="otherPanel.icon" />
                    <span>{{ otherPanel.title }} Panel</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
