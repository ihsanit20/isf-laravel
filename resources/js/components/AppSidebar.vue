<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    BookMarked,
    FileBadge2,
    Info,
    LayoutGrid,
    Landmark,
    ScrollText,
    SquarePen,
    UserRound,
    Users,
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
import type { NavItem, UserRole } from '@/types';

const adminRoles: UserRole[] = ['admin', 'super_admin'];

const page = usePage();

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'My Membership',
            href: '/my-membership',
            icon: UserRound,
        },
        {
            title: 'My Deposits',
            href: '/my-deposits',
            icon: WalletCards,
        },
    ];

    if (adminRoles.includes(page.props.auth.user.role)) {
        items.push({
            title: 'Member List',
            href: '/admin/members',
            icon: Users,
        });

        items.push({
            title: 'User List',
            href: '/admin/users',
            icon: SquarePen,
        });

        items.push({
            title: 'Fund Cycles',
            href: '/admin/fund-cycles',
            icon: Landmark,
        });

        items.push({
            title: 'Charge Categories',
            href: '/admin/charge-categories',
            icon: BookMarked,
        });

        items.push({
            title: 'Charge List',
            href: '/admin/charges',
            icon: BadgeDollarSign,
        });

        items.push({
            title: 'Deposit Reviews',
            href: '/admin/deposits',
            icon: FileBadge2,
        });
    }

    return items;
});

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
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
