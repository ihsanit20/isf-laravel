<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, CircleCheck } from 'lucide-vue-next';
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

type Props = {
    personal: PersonalDashboard;
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
    </div>
</template>
