<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    CalendarDays,
    Clock3,
    Layers3,
    ReceiptText,
    Shield,
    UsersRound,
    WalletCards,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { dashboard } from '@/routes';

type ActivityTone = 'success' | 'warning' | 'danger';
type ActivityItem = {
    id: string;
    title: string;
    description: string;
    timestamp: string | null;
    tone: ActivityTone;
};

type PersonalDashboard = {
    summary: {
        total_members: number;
        approved_members: number;
        active_members: number;
        total_units: number;
        verified_deposits: number;
        available_balance: number;
    };
    actions: {
        pending_deposit_count: number;
        my_charge_count: number;
        pending_charge_count: number;
        my_cycle_allocation_count: number;
        open_cycle_count: number;
    };
    next_cycle: {
        name: string;
        lock_date: string | null;
        unit_amount: number;
        status_label: string;
    } | null;
    recent_activity: ActivityItem[];
};

type AdminOverview = {
    pool_summary: {
        total_verified_deposits: number;
        total_charge_allocations: number;
        total_cycle_allocations: number;
        remaining_pool: number;
    };
    queues: {
        pending_deposits: number;
        pending_members: number;
        approved_not_activated_members: number;
        pending_charges: number;
    };
    cycle_statuses: Array<{
        status: string;
        label: string;
        count: number;
    }>;
    recent_activity: ActivityItem[];
};

type Props = {
    personal: PersonalDashboard;
    adminOverview: AdminOverview | null;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

const props = defineProps<Props>();

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const activityVariant = (
    tone: ActivityTone,
): 'default' | 'secondary' | 'destructive' => {
    if (tone === 'success') {
        return 'default';
    }

    if (tone === 'danger') {
        return 'destructive';
    }

    return 'secondary';
};

const cycleStatusVariant = (
    status: string,
): 'default' | 'secondary' | 'outline' => {
    if (status === 'open') {
        return 'default';
    }

    if (status === 'locked' || status === 'matured') {
        return 'secondary';
    }

    return 'outline';
};

const personalStats = computed(() => [
    {
        label: 'My Members',
        value: props.personal.summary.total_members.toLocaleString(),
        note: `${props.personal.summary.approved_members} approved, ${props.personal.summary.active_members} active`,
        icon: UsersRound,
    },
    {
        label: 'My Units',
        value: props.personal.summary.total_units.toLocaleString(),
        note: 'Total units across your member accounts',
        icon: BadgeCheck,
    },
    {
        label: 'My Verified Deposits',
        value: money(props.personal.summary.verified_deposits),
        note: 'Only verified deposits enter the pool',
        icon: WalletCards,
    },
    {
        label: 'My Available Balance',
        value: money(props.personal.summary.available_balance),
        note: 'After charge and cycle allocations',
        icon: Layers3,
    },
]);

const personalActions = computed(() => [
    {
        label: 'My Pending Deposits',
        value: props.personal.actions.pending_deposit_count,
        note: 'Waiting for admin verification',
    },
    {
        label: 'My Charges',
        value: props.personal.actions.my_charge_count,
        note:
            props.personal.actions.pending_charge_count > 0
                ? `${props.personal.actions.pending_charge_count} pending across your members`
                : 'No pending charges right now',
    },
    {
        label: 'My Cycle Allocations',
        value: props.personal.actions.my_cycle_allocation_count,
        note: 'Already posted from your pool',
    },
    {
        label: 'Open Fund Cycles',
        value: props.personal.actions.open_cycle_count,
        note: 'Cycles available across the system',
    },
]);

const adminPoolStats = computed(() => {
    if (!props.adminOverview) {
        return [];
    }

    return [
        {
            label: 'Verified Deposit Pool',
            value: money(
                props.adminOverview.pool_summary.total_verified_deposits,
            ),
        },
        {
            label: 'Charge Allocations',
            value: money(
                props.adminOverview.pool_summary.total_charge_allocations,
            ),
        },
        {
            label: 'Cycle Allocations',
            value: money(
                props.adminOverview.pool_summary.total_cycle_allocations,
            ),
        },
        {
            label: 'Remaining Pool',
            value: money(props.adminOverview.pool_summary.remaining_pool),
        },
    ];
});

const adminQueueStats = computed(() => {
    if (!props.adminOverview) {
        return [];
    }

    return [
        {
            label: 'Pending Deposit Reviews',
            value: props.adminOverview.queues.pending_deposits,
        },
        {
            label: 'Pending Member Approvals',
            value: props.adminOverview.queues.pending_members,
        },
        {
            label: 'Approved Not Activated',
            value: props.adminOverview.queues.approved_not_activated_members,
        },
        {
            label: 'Pending Charges',
            value: props.adminOverview.queues.pending_charges,
        },
    ];
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-emerald-50 p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        My ISF
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                        Personal balance and member activity in one place
                    </h1>
                    <p
                        class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground"
                    >
                        Your dashboard tracks membership, deposit verification,
                        charge settlement, and fund cycle participation without
                        mixing personal and admin operations.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button as-child>
                        <Link href="/my-membership">My Membership</Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link href="/my-deposits">My Deposits</Link>
                    </Button>
                    <Button v-if="adminOverview" as-child variant="outline">
                        <Link href="/admin/deposits">Admin Deposits</Link>
                    </Button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                v-for="stat in personalStats"
                :key="stat.label"
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <component
                    :is="stat.icon"
                    class="size-5 text-muted-foreground"
                />
                <p class="mt-4 text-xs text-muted-foreground">
                    {{ stat.label }}
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ stat.value }}
                </p>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ stat.note }}
                </p>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
            <article
                class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <ReceiptText class="size-5 text-muted-foreground" />
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">
                            Action Needed
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Current items affecting your membership flow.
                        </p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <div
                        v-for="item in personalActions"
                        :key="item.label"
                        class="rounded-3xl border border-sidebar-border/70 bg-muted/20 px-4 py-4"
                    >
                        <p class="text-xs text-muted-foreground">
                            {{ item.label }}
                        </p>
                        <p class="mt-2 text-2xl font-semibold text-foreground">
                            {{ item.value.toLocaleString() }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ item.note }}
                        </p>
                    </div>
                </div>
            </article>

            <article
                class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
            >
                <div class="flex items-center gap-3">
                    <CalendarDays class="size-5 text-muted-foreground" />
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">
                            Next Fund Cycle
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Closest open cycle relevant to allocation planning.
                        </p>
                    </div>
                </div>

                <div v-if="personal.next_cycle" class="mt-5 space-y-4">
                    <div>
                        <p class="text-xl font-semibold text-foreground">
                            {{ personal.next_cycle.name }}
                        </p>
                        <div class="mt-2 flex items-center gap-2">
                            <Badge>{{
                                personal.next_cycle.status_label
                            }}</Badge>
                            <span class="text-sm text-muted-foreground">
                                Unit amount
                                {{ money(personal.next_cycle.unit_amount) }}
                            </span>
                        </div>
                    </div>

                    <div
                        class="rounded-3xl border border-sidebar-border/70 bg-muted/20 px-4 py-4"
                    >
                        <p class="text-xs text-muted-foreground">Lock date</p>
                        <p class="mt-2 text-lg font-semibold text-foreground">
                            {{
                                personal.next_cycle.lock_date || 'Not fixed yet'
                            }}
                        </p>
                    </div>

                    <Button
                        as-child
                        variant="outline"
                        class="w-full justify-center"
                    >
                        <Link href="/my-membership"
                            >Review eligible members</Link
                        >
                    </Button>
                </div>

                <div
                    v-else
                    class="mt-5 rounded-3xl border border-dashed border-sidebar-border/70 bg-muted/10 px-4 py-6 text-sm text-muted-foreground"
                >
                    No open fund cycle is available right now.
                </div>
            </article>
        </section>

        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
        >
            <div class="flex items-center gap-3">
                <Clock3 class="size-5 text-muted-foreground" />
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        Recent Personal Activity
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Latest membership, deposit, and allocation events.
                    </p>
                </div>
            </div>

            <div
                v-if="personal.recent_activity.length > 0"
                class="mt-5 space-y-3"
            >
                <article
                    v-for="item in personal.recent_activity"
                    :key="item.id"
                    class="flex flex-col gap-3 rounded-3xl border border-sidebar-border/70 bg-muted/15 px-4 py-4 md:flex-row md:items-start md:justify-between"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <p class="font-medium text-foreground">
                                {{ item.title }}
                            </p>
                            <Badge :variant="activityVariant(item.tone)">
                                {{ item.tone }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ item.description }}
                        </p>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        {{ item.timestamp || 'No timestamp' }}
                    </p>
                </article>
            </div>

            <div
                v-else
                class="mt-5 rounded-3xl border border-dashed border-sidebar-border/70 bg-muted/10 px-4 py-6 text-sm text-muted-foreground"
            >
                No personal activity is available yet.
            </div>
        </section>

        <section
            v-if="adminOverview"
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-amber-50 p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
            >
                <div>
                    <div class="flex items-center gap-3">
                        <Shield class="size-5 text-muted-foreground" />
                        <h2 class="text-xl font-semibold tracking-tight">
                            Admin Overview
                        </h2>
                    </div>
                    <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Operational data stays separate here so you can review
                        queues, pool movement, and cycle progress without mixing
                        them with your personal membership state.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button as-child variant="outline">
                        <Link href="/admin/members">Admin Members</Link>
                    </Button>
                    <Button as-child variant="outline">
                        <Link href="/admin/fund-cycles">Fund Cycles</Link>
                    </Button>
                </div>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="stat in adminPoolStats"
                    :key="stat.label"
                    class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
                >
                    <p class="text-xs text-muted-foreground">
                        {{ stat.label }}
                    </p>
                    <p class="mt-2 text-xl font-semibold text-foreground">
                        {{ stat.value }}
                    </p>
                </article>
            </div>

            <div class="mt-4 grid gap-4 xl:grid-cols-[0.9fr_1.1fr]">
                <article
                    class="rounded-3xl border border-sidebar-border/70 bg-background p-5 shadow-sm"
                >
                    <h3 class="text-base font-semibold tracking-tight">
                        Review Queues
                    </h3>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="item in adminQueueStats"
                            :key="item.label"
                            class="flex items-center justify-between rounded-2xl border border-sidebar-border/70 bg-muted/15 px-4 py-3"
                        >
                            <p class="text-sm text-muted-foreground">
                                {{ item.label }}
                            </p>
                            <p class="text-lg font-semibold text-foreground">
                                {{ item.value.toLocaleString() }}
                            </p>
                        </div>
                    </div>
                </article>

                <article
                    class="rounded-3xl border border-sidebar-border/70 bg-background p-5 shadow-sm"
                >
                    <h3 class="text-base font-semibold tracking-tight">
                        Cycle Status
                    </h3>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <div
                            v-for="item in adminOverview.cycle_statuses"
                            :key="item.status"
                            class="min-w-35 flex-1 rounded-2xl border border-sidebar-border/70 bg-muted/15 px-4 py-4"
                        >
                            <Badge :variant="cycleStatusVariant(item.status)">
                                {{ item.label }}
                            </Badge>
                            <p
                                class="mt-3 text-2xl font-semibold text-foreground"
                            >
                                {{ item.count.toLocaleString() }}
                            </p>
                        </div>
                    </div>
                </article>
            </div>

            <article
                class="mt-4 rounded-3xl border border-sidebar-border/70 bg-background p-5 shadow-sm"
            >
                <h3 class="text-base font-semibold tracking-tight">
                    Recent Admin Activity
                </h3>

                <div
                    v-if="adminOverview.recent_activity.length > 0"
                    class="mt-4 space-y-3"
                >
                    <div
                        v-for="item in adminOverview.recent_activity"
                        :key="item.id"
                        class="flex flex-col gap-3 rounded-2xl border border-sidebar-border/70 bg-muted/15 px-4 py-4 md:flex-row md:items-start md:justify-between"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-foreground">
                                    {{ item.title }}
                                </p>
                                <Badge :variant="activityVariant(item.tone)">
                                    {{ item.tone }}
                                </Badge>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ item.description }}
                            </p>
                        </div>

                        <p class="text-xs text-muted-foreground">
                            {{ item.timestamp || 'No timestamp' }}
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="mt-4 rounded-2xl border border-dashed border-sidebar-border/70 bg-muted/10 px-4 py-6 text-sm text-muted-foreground"
                >
                    No recent admin activity is available yet.
                </div>
            </article>
        </section>
    </div>
</template>
