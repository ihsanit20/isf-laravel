<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { CalendarDays, Landmark, Layers3, UserRound } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

type AllocationItem = {
    id: number;
    member_name: string | null;
    cycle_name: string | null;
    cycle_status: string | null;
    slot_key: string | null;
    amount: number;
    allocated_at: string | null;
    notes: string | null;
};

type Props = {
    summary: {
        total_allocations: number;
        total_allocated_amount: number;
        member_count: number;
        cycle_count: number;
    };
    allocations: AllocationItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Allocations',
                href: '/my-allocations',
            },
        ],
    },
});

defineProps<Props>();

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const cycleStatusVariant = (
    status: string | null,
): 'default' | 'secondary' | 'outline' => {
    if (status === 'open') {
        return 'default';
    }

    if (status === 'locked' || status === 'matured') {
        return 'secondary';
    }

    return 'outline';
};

const cycleStatusLabel = (status: string | null): string => {
    if (!status) {
        return 'Unknown';
    }

    return status
        .replace('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
};
</script>

<template>
    <Head title="My Allocations" />

    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-sky-50 p-6 shadow-sm"
        >
            <div class="max-w-3xl">
                <p
                    class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                >
                    My Allocations
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                    All fund cycle allocations across your members
                </h1>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Review which member joined which cycle, in which slot, for
                    how much, and when the allocation was posted.
                </p>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <Layers3 class="size-5 text-muted-foreground" />
                <p class="mt-4 text-xs text-muted-foreground">
                    My Total Allocations
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ summary.total_allocations.toLocaleString() }}
                </p>
            </article>

            <article
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <Landmark class="size-5 text-muted-foreground" />
                <p class="mt-4 text-xs text-muted-foreground">
                    My Allocated Amount
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ money(summary.total_allocated_amount) }}
                </p>
            </article>

            <article
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <UserRound class="size-5 text-muted-foreground" />
                <p class="mt-4 text-xs text-muted-foreground">
                    Members Involved
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ summary.member_count.toLocaleString() }}
                </p>
            </article>

            <article
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <CalendarDays class="size-5 text-muted-foreground" />
                <p class="mt-4 text-xs text-muted-foreground">
                    Cycles Involved
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ summary.cycle_count.toLocaleString() }}
                </p>
            </article>
        </section>

        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-background shadow-sm"
        >
            <div class="border-b border-sidebar-border/70 px-6 py-5">
                <h2
                    class="text-lg font-semibold tracking-tight text-foreground"
                >
                    Allocation List
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Every allocation made for your managed members appears here.
                </p>
            </div>

            <div v-if="allocations.length > 0" class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                >
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Member</th>
                            <th class="px-4 py-3 font-medium">Fund Cycle</th>
                            <th class="px-4 py-3 font-medium">Slot</th>
                            <th class="px-4 py-3 font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Allocated At</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="allocation in allocations"
                            :key="allocation.id"
                            class="align-top"
                        >
                            <td class="px-4 py-4">
                                <p class="font-medium text-foreground">
                                    {{
                                        allocation.member_name ||
                                        'Unknown member'
                                    }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Allocation #{{ allocation.id }}
                                </p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-medium text-foreground">
                                    {{
                                        allocation.cycle_name || 'Unknown cycle'
                                    }}
                                </p>
                                <Badge
                                    class="mt-2"
                                    :variant="
                                        cycleStatusVariant(
                                            allocation.cycle_status,
                                        )
                                    "
                                >
                                    {{
                                        cycleStatusLabel(
                                            allocation.cycle_status,
                                        )
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-medium text-foreground">
                                    {{ allocation.slot_key || 'No slot' }}
                                </p>
                                <p
                                    v-if="allocation.notes"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ allocation.notes }}
                                </p>
                            </td>
                            <td class="px-4 py-4 font-medium text-foreground">
                                {{ money(allocation.amount) }}
                            </td>
                            <td class="px-4 py-4 text-muted-foreground">
                                {{ allocation.allocated_at || 'Not recorded' }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="px-6 py-10 text-sm text-muted-foreground">
                No fund cycle allocations are available for your members yet.
            </div>
        </section>
    </div>
</template>
