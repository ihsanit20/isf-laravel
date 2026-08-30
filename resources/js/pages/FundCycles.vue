<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Layers3 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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
        breadcrumbs: [
            {
                title: 'Fund Cycles',
                href: '/fund-cycles',
            },
        ],
    },
});

defineProps<Props>();

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;

const statusVariant = (
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'open') {
        return 'default';
    }

    if (status === 'settled') {
        return 'secondary';
    }

    return 'outline';
};
</script>

<template>
    <Head title="Fund Cycles" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="max-w-2xl">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Fund Cycles
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Browse fund cycles, their timelines, total allocations, and
                    your own allocation in each cycle.
                </p>
            </div>
        </section>

        <section v-if="fundCycles.length > 0" class="grid gap-4">
            <article
                v-for="fundCycle in fundCycles"
                :key="fundCycle.id"
                class="rounded-[26px] border border-sidebar-border/70 bg-background p-5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Cycle #{{ fundCycle.id }}
                        </p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight">
                            {{ fundCycle.name }}
                        </h2>
                    </div>

                    <Badge :variant="statusVariant(fundCycle.status)">
                        {{ fundCycle.status_label }}
                    </Badge>
                </div>

                <div class="mt-5 grid gap-3 text-sm md:grid-cols-2">
                    <div class="rounded-2xl bg-background/75 px-3 py-3">
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <CalendarDays class="size-4" />
                            Timeline
                        </div>
                        <div class="mt-2 space-y-1 text-foreground">
                            <p>
                                Start: {{ fundCycle.start_date || 'Not set' }}
                            </p>
                            <p>Lock: {{ fundCycle.lock_date || 'Not set' }}</p>
                            <p>
                                Maturity:
                                {{ fundCycle.maturity_date || 'Not set' }}
                            </p>
                            <p>
                                Settlement:
                                {{ fundCycle.settlement_date || 'Not set' }}
                            </p>
                        </div>
                    </div>
                    <div class="rounded-2xl bg-background/75 px-3 py-3">
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Layers3 class="size-4" />
                            Allocation
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Unit amount: {{ money(fundCycle.unit_amount) }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Total allocation:
                            {{ money(fundCycle.total_allocated_amount) }}
                        </p>
                        <p class="mt-1 font-medium text-foreground">
                            My allocation:
                            {{ money(fundCycle.my_allocated_amount) }}
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <Button as-child variant="outline" size="sm">
                        <Link :href="`/fund-cycles/${fundCycle.id}`">
                            View Details
                        </Link>
                    </Button>
                </div>
            </article>
        </section>

        <section
            v-else
            class="rounded-[28px] border border-dashed border-sidebar-border/80 bg-background p-10 text-center shadow-sm"
        >
            <div class="mx-auto max-w-md">
                <p
                    class="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase"
                >
                    No Fund Cycles
                </p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                    No fund cycles found
                </h2>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Fund cycles will appear here once they are published.
                </p>
            </div>
        </section>
    </div>
</template>
