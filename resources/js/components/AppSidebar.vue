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
import type { NavGroup, NavItem, UserRole } from '@/types';

const adminRoles: UserRole[] = ['admin', 'super_admin'];

const page = usePage();

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
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain label="My Fund" :items="memberNavItems" />
            <template v-if="isAdmin">
                <NavMain
                    v-for="group in adminNavGroups"
                    :key="group.label"
                    :label="group.label"
                    :items="group.items"
                />
            </template>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
