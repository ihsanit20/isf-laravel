<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CircleCheck, Shield } from 'lucide-vue-next';
import { computed } from 'vue';
import ActivityList from '@/components/shared/ActivityList.vue';
import type { ActivityItem } from '@/components/shared/ActivityList.vue';
import MoneyFlow from '@/components/shared/MoneyFlow.vue';
import type { MoneyFlowStep } from '@/components/shared/MoneyFlow.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';

type PersonalMember = {
    id: number;
    full_name: string;
    status: string;
    is_active: boolean;
    units: number;
    capital_in_cycles: number;
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
    balance: {
        deposits: number;
        fees: number;
        cycle_allocations: number;
        cycle_returns: number;
        payouts: number;
        available: number;
    };
    members: PersonalMember[];
    recent_activity: ActivityItem[];
};

type AdminOverview = {
    pool_summary: {
        total_verified_deposits: number;
        total_charge_allocations: number;
        total_cycle_allocations: number;
        total_cycle_returns: number;
        total_payouts: number;
        remaining_pool: number;
        platform_fund: number;
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
        breadcrumbs: [{ title: 'Overview', href: dashboard() }],
    },
});

const props = defineProps<Props>();

const flowSteps = computed<MoneyFlowStep[]>(() => {
    const balance = props.personal.balance;

    return [
        { label: 'Deposited', amount: balance.deposits, hint: 'Verified' },
        { label: 'Charges', amount: balance.fees, sign: '−' },
        { label: 'Invested', amount: balance.cycle_allocations, sign: '−' },
        {
            label: 'Returned',
            amount: balance.cycle_returns,
            sign: '+',
            hint: 'From settled cycles',
        },
        { label: 'Withdrawn', amount: balance.payouts, sign: '−' },
        {
            label: 'Available',
            amount: balance.available,
            highlight: true,
        },
    ];
});

type NextStep = {
    key: string;
    title: string;
    description: string;
    href: string;
    action: string;
};

const nextSteps = computed<NextStep[]>(() => {
    const { summary, actions, next_cycle, balance } = props.personal;
    const steps: NextStep[] = [];

    if (summary.total_members === 0) {
        steps.push({
            key: 'member',
            title: 'Add your first member',
            description:
                'Investments are made per member. Apply for yourself or a family member.',
            href: '/my-membership/create',
            action: 'Add member',
        });
    }

    if (actions.pending_deposit_count > 0) {
        steps.push({
            key: 'pending-deposits',
            title: `${actions.pending_deposit_count} deposit(s) waiting for verification`,
            description: 'An admin will verify them soon.',
            href: '/my-deposits',
            action: 'View',
        });
    } else if (balance.deposits === 0) {
        steps.push({
            key: 'deposit',
            title: 'Make your first deposit',
            description: 'Send money to the ISF account and submit the proof.',
            href: '/my-deposits/create',
            action: 'New deposit',
        });
    }

    if (actions.pending_charge_count > 0) {
        steps.push({
            key: 'charges',
            title: `${actions.pending_charge_count} unpaid charge(s)`,
            description:
                'Pay them from your balance. The registration fee activates a member.',
            href: '/my-membership',
            action: 'Pay',
        });
    }

    if (next_cycle && summary.active_members > 0) {
        steps.push({
            key: 'cycle',
            title: `${next_cycle.name} is open`,
            description: next_cycle.lock_date
                ? `Invest before ${next_cycle.lock_date}.`
                : 'Taking investments now.',
            href: '/my-allocations',
            action: 'Invest',
        });
    }

    return steps;
});

const adminQueues = computed(() => {
    if (!props.adminOverview) {
        return [];
    }

    const queues = props.adminOverview.queues;

    return [
        {
            label: 'Deposits to verify',
            value: queues.pending_deposits,
            href: '/admin/deposits',
        },
        {
            label: 'Members to approve',
            value: queues.pending_members,
            href: '/admin/members',
        },
        {
            label: 'Approved, not activated',
            value: queues.approved_not_activated_members,
            href: '/admin/members',
        },
        {
            label: 'Unpaid charges',
            value: queues.pending_charges,
            href: '/admin/charges',
        },
    ];
});

const adminPool = computed(() => {
    if (!props.adminOverview) {
        return [];
    }

    const pool = props.adminOverview.pool_summary;

    return [
        { label: 'Verified deposits', value: pool.total_verified_deposits },
        { label: 'Charges paid', value: pool.total_charge_allocations },
        { label: 'Invested in cycles', value: pool.total_cycle_allocations },
        { label: 'Returned from cycles', value: pool.total_cycle_returns },
        { label: 'Paid out', value: pool.total_payouts },
        { label: 'Members’ available', value: pool.remaining_pool },
        { label: 'Platform fund', value: pool.platform_fund },
    ];
});
</script>

<template>
    <Head title="Overview" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Overview"
            description="Where your money is: deposited, invested in cycles, returned, and available."
        />

        <MoneyFlow :steps="flowSteps" />

        <div class="grid gap-4 xl:grid-cols-[1.2fr_1fr]">
            <Card class="gap-4">
                <CardHeader>
                    <CardTitle>Next steps</CardTitle>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="nextSteps.length === 0"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <CircleCheck class="size-4 text-emerald-600" />
                        All caught up.
                    </div>
                    <ul v-else class="divide-y">
                        <li
                            v-for="step in nextSteps"
                            :key="step.key"
                            class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0"
                        >
                            <div class="min-w-0">
                                <p class="font-medium">{{ step.title }}</p>
                                <p class="text-sm text-muted-foreground">
                                    {{ step.description }}
                                </p>
                            </div>
                            <Button as-child size="sm" variant="outline">
                                <Link :href="step.href">{{ step.action }}</Link>
                            </Button>
                        </li>
                    </ul>
                </CardContent>
            </Card>

            <Card class="gap-4">
                <CardHeader>
                    <div class="flex items-center justify-between">
                        <CardTitle>Members</CardTitle>
                        <Button as-child size="sm" variant="ghost">
                            <Link href="/my-membership">
                                All
                                <ArrowRight class="size-4" />
                            </Link>
                        </Button>
                    </div>
                    <CardDescription>
                        {{ props.personal.summary.active_members }} active of
                        {{ props.personal.summary.total_members }} ·
                        {{ props.personal.summary.total_units }} units
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <p
                        v-if="props.personal.members.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No members yet.
                    </p>
                    <ul v-else class="divide-y">
                        <li
                            v-for="member in props.personal.members"
                            :key="member.id"
                            class="flex items-center justify-between gap-3 py-2 first:pt-0 last:pb-0"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium">
                                    {{ member.full_name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ formatMoney(member.capital_in_cycles) }}
                                    in cycles
                                </p>
                            </div>
                            <StatusBadge
                                :status="
                                    member.is_active ? 'active' : member.status
                                "
                            />
                        </li>
                    </ul>
                </CardContent>
            </Card>
        </div>

        <Card class="gap-4">
            <CardHeader>
                <CardTitle>Recent activity</CardTitle>
            </CardHeader>
            <CardContent>
                <ActivityList :items="props.personal.recent_activity" />
            </CardContent>
        </Card>

        <section v-if="props.adminOverview" class="grid gap-4">
            <div class="flex items-center gap-2 pt-2">
                <Shield class="size-5 text-muted-foreground" />
                <h2 class="text-lg font-semibold">Admin</h2>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="queue in adminQueues"
                    :key="queue.label"
                    :href="queue.href"
                    :class="
                        cn(
                            'rounded-xl border bg-card p-4 shadow-xs transition-colors hover:border-foreground/20',
                            queue.value > 0 &&
                                'border-amber-300 dark:border-amber-800',
                        )
                    "
                >
                    <p class="text-sm text-muted-foreground">
                        {{ queue.label }}
                    </p>
                    <p class="mt-2 text-2xl font-semibold tabular-nums">
                        {{ queue.value }}
                    </p>
                </Link>
            </div>

            <div class="grid gap-4 xl:grid-cols-[1.4fr_1fr]">
                <Card class="gap-4">
                    <CardHeader>
                        <CardTitle>Member money</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2">
                            <div
                                v-for="item in adminPool"
                                :key="item.label"
                                class="flex justify-between gap-2 text-sm"
                            >
                                <dt class="text-muted-foreground">
                                    {{ item.label }}
                                </dt>
                                <dd class="font-medium tabular-nums">
                                    {{ formatMoney(item.value) }}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card class="gap-4">
                    <CardHeader>
                        <div class="flex items-center justify-between">
                            <CardTitle>Fund cycles</CardTitle>
                            <Button as-child size="sm" variant="ghost">
                                <Link href="/admin/fund-cycles">
                                    Manage
                                    <ArrowRight class="size-4" />
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="flex flex-wrap gap-2">
                        <div
                            v-for="item in props.adminOverview.cycle_statuses"
                            :key="item.status"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2"
                        >
                            <StatusBadge
                                :status="item.status"
                                :label="item.label"
                            />
                            <span class="font-semibold tabular-nums">
                                {{ item.count }}
                            </span>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <Card class="gap-4">
                <CardHeader>
                    <CardTitle>Recent admin activity</CardTitle>
                </CardHeader>
                <CardContent>
                    <ActivityList
                        :items="props.adminOverview.recent_activity"
                    />
                </CardContent>
            </Card>
        </section>
    </div>
</template>
