<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    BookMarked,
    Briefcase,
    CalendarDays,
    Cog,
    FileBadge2,
    HandCoins,
    Info,
    Landmark,
    LayoutGrid,
    Layers3,
    NotebookTabs,
    Scale,
    ScrollText,
    Tags,
    TrendingUp,
    UserCog,
    Users,
    UsersRound,
    Wallet,
    WalletCards,
} from 'lucide-vue-next';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import PanelSwitcher from '@/components/PanelSwitcher.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { dashboard } from '@/routes';
import { dashboard as adminDashboard } from '@/routes/admin';
import type { NavGroup, NavItem, UserRole } from '@/types';

const adminRoles: UserRole[] = ['admin', 'super_admin'];

const page = usePage();
const { currentUrl } = useCurrentUrl();

const memberNavItems: NavItem[] = [
    { title: 'Overview', href: dashboard(), icon: LayoutGrid },
    { title: 'Deposits', href: '/my-deposits', icon: WalletCards },
    { title: 'Members', href: '/my-membership', icon: UsersRound },
    { title: 'Investments', href: '/my-allocations', icon: Layers3 },
    {
        title: 'Fund Cycles',
        href: '/fund-cycles',
        icon: Landmark,
        matchPrefix: true,
    },
    { title: 'Wallet', href: '/my-wallet', icon: Wallet },
];

const adminNavGroups: NavGroup[] = [
    {
        label: 'Admin',
        items: [
            { title: 'Overview', href: adminDashboard(), icon: LayoutGrid },
        ],
    },
    {
        label: 'Reviews',
        items: [
            { title: 'Deposits', href: '/admin/deposits', icon: FileBadge2 },
            { title: 'Members', href: '/admin/members', icon: Users },
            { title: 'Payouts', href: '/admin/payouts', icon: HandCoins },
            {
                title: 'Charges',
                href: '/admin/charges',
                icon: BadgeDollarSign,
            },
        ],
    },
    {
        label: 'Fund',
        items: [
            {
                title: 'Fund Cycles',
                href: '/admin/fund-cycles',
                icon: Landmark,
                matchPrefix: true,
            },
            {
                title: 'Events',
                href: '/admin/events',
                icon: CalendarDays,
                matchPrefix: true,
            },
            {
                title: 'Businesses',
                href: '/admin/businesses',
                icon: Briefcase,
                matchPrefix: true,
            },
        ],
    },
    {
        label: 'Accounts',
        items: [
            { title: 'Accounts', href: '/admin/accounts', icon: Scale },
            {
                title: 'Journal',
                href: '/admin/accounts/journal',
                icon: NotebookTabs,
            },
            {
                title: 'Incomes',
                href: '/admin/general-incomes',
                icon: TrendingUp,
            },
            {
                title: 'Expenses',
                href: '/admin/general-expenses',
                icon: BookMarked,
            },
            {
                title: 'Charge Categories',
                href: '/admin/charge-categories',
                icon: Tags,
            },
        ],
    },
    {
        label: 'System',
        items: [
            { title: 'Users', href: '/admin/users', icon: UserCog },
            { title: 'Settings', href: '/admin/settings', icon: Cog },
        ],
    },
];

const isAdmin = computed(() => adminRoles.includes(page.props.auth.user.role));

const isAdminPanel = computed(
    () =>
        isAdmin.value &&
        (currentUrl.value === '/admin' ||
            currentUrl.value.startsWith('/admin/')),
);

const homeHref = computed(() =>
    isAdminPanel.value ? adminDashboard() : dashboard(),
);

const footerNavItems: NavItem[] = [
    {
        title: 'About Us',
        href: '/about-isf',
        icon: Info,
    },
    {
        title: 'Terms & Conditions',
        href: '/terms-and-conditions',
        icon: ScrollText,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="homeHref">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
            <PanelSwitcher v-if="isAdmin" :is-admin-panel="isAdminPanel" />
        </SidebarHeader>

        <SidebarContent>
            <template v-if="isAdminPanel">
                <NavMain
                    v-for="group in adminNavGroups"
                    :key="group.label"
                    :label="group.label"
                    :items="group.items"
                />
            </template>
            <NavMain v-else label="My Fund" :items="memberNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter v-if="!isAdminPanel" :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
