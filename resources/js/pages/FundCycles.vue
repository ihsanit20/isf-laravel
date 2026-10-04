<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, Landmark } from 'lucide-vue-next';
import { computed } from 'vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { formatMoney } from '@/lib/format';

type FundCycleItem = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    unit_amount: number;
    start_date: string | null;
    lock_date: string | null;
    maturity_date: string | null;
    settlement_date: string | null;
    allocations_count: number;
    total_allocated_amount: number;
    my_allocated_amount: number;
};

type Props = {
    fundCycles: FundCycleItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Fund Cycles', href: '/fund-cycles' }],
    },
});

const props = defineProps<Props>();

const groups = computed(() =>
    [
        {
            title: 'Open',
            description: 'Taking new investments until the lock date.',
            cycles: props.fundCycles.filter((cycle) => cycle.status === 'open'),
        },
        {
            title: 'Running',
            description:
                'Money is working in events and businesses. Results come in as each one finishes.',
            cycles: props.fundCycles.filter((cycle) =>
                ['locked', 'matured'].includes(cycle.status),
            ),
        },
        {
            title: 'Settled',
            description:
                'Capital and profit or loss returned to each member’s balance.',
            cycles: props.fundCycles.filter(
                (cycle) => cycle.status === 'settled',
            ),
        },
    ].filter((group) => group.cycles.length > 0),
);
</script>

<template>
    <Head title="Fund Cycles" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Fund Cycles"
            description="Members pool money into a cycle. The cycle runs events and businesses, then settles and returns capital plus each member’s share of the result."
        />

        <EmptyState
            v-if="groups.length === 0"
            :icon="Landmark"
            title="No fund cycles yet"
            description="Cycles appear here once an admin opens one."
        />

        <section v-for="group in groups" :key="group.title" class="grid gap-3">
            <div>
                <h2 class="text-lg font-semibold">{{ group.title }}</h2>
                <p class="text-sm text-muted-foreground">
                    {{ group.description }}
                </p>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="cycle in group.cycles"
                    :key="cycle.id"
                    :href="`/fund-cycles/${cycle.id}`"
                    class="group rounded-xl focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <Card
                        class="h-full gap-4 transition-colors group-hover:border-foreground/20"
                    >
                        <CardHeader>
                            <div class="flex items-start justify-between gap-2">
                                <CardTitle class="text-base">
                                    {{ cycle.name }}
                                </CardTitle>
                                <StatusBadge
                                    :status="cycle.status"
                                    :label="cycle.status_label"
                                />
                            </div>
                            <CardDescription>
                                {{ formatMoney(cycle.unit_amount) }} per unit
                                per month
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="grid gap-3 text-sm">
                            <dl class="grid grid-cols-2 gap-3">
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        My investment
                                    </dt>
                                    <dd class="font-medium tabular-nums">
                                        {{
                                            formatMoney(
                                                cycle.my_allocated_amount,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-muted-foreground">
                                        Cycle total
                                    </dt>
                                    <dd class="font-medium tabular-nums">
                                        {{
                                            formatMoney(
                                                cycle.total_allocated_amount,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="cycle.status === 'open'">
                                    Locks {{ cycle.lock_date ?? 'not set' }}
                                </template>
                                <template
                                    v-else-if="cycle.status === 'settled'"
                                >
                                    Settled
                                    {{ cycle.settlement_date ?? '' }}
                                </template>
                                <template v-else>
                                    Matures
                                    {{ cycle.maturity_date ?? 'not set' }}
                                </template>
                                · {{ cycle.allocations_count }} allocations
                            </p>
                        </CardContent>
                        <CardFooter
                            class="mt-auto text-sm font-medium text-muted-foreground group-hover:text-foreground"
                        >
                            View details
                            <ArrowRight class="ml-1 size-4" />
                        </CardFooter>
                    </Card>
                </Link>
            </div>
        </section>
    </div>
</template>
