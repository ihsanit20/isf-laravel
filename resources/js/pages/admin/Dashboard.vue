<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from 'lucide-vue-next';
import { computed } from 'vue';
import ActivityList from '@/components/shared/ActivityList.vue';
import type { ActivityItem } from '@/components/shared/ActivityList.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';

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
    overview: AdminOverview;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Admin Overview', href: dashboard() }],
    },
});

const props = defineProps<Props>();

const queues = computed(() => {
    const queues = props.overview.queues;

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

const pool = computed(() => {
    const pool = props.overview.pool_summary;

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
    <Head title="Admin Overview" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Admin Overview"
            description="Review queues, member money and fund cycle status."
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <Link
                v-for="queue in queues"
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
                            v-for="item in pool"
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
                        v-for="item in props.overview.cycle_statuses"
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
                <ActivityList :items="props.overview.recent_activity" />
            </CardContent>
        </Card>
    </div>
</template>
