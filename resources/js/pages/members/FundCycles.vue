<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Layers3 } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type MemberItem = {
    id: number;
    full_name: string;
    status: string;
    approved_at: string | null;
    activated_at: string | null;
};

type FundCycleItem = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    start_date: string | null;
    lock_date: string | null;
    maturity_date: string | null;
    settlement_date: string | null;
    slots: string[];
    allocations_count: number;
    has_member_allocation: boolean;
};

type Props = {
    member: MemberItem;
    fundCycles: FundCycleItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Membership',
                href: '/my-membership',
            },
            {
                title: 'Available Fund Cycles',
                href: '#',
            },
        ],
    },
});

defineProps<Props>();
</script>

<template>
    <Head title="Available Fund Cycles" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-3xl border border-sidebar-border/70 bg-background p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-2xl">
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Eligible Member
                    </p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight">
                        {{ member.full_name }}
                    </h1>
                    <p
                        class="mt-2 max-w-xl text-sm leading-6 text-muted-foreground"
                    >
                        Open fund cycles available for member allocation are
                        listed here.
                    </p>
                </div>

                <Button as-child variant="outline" class="shrink-0">
                    <Link href="/my-membership">
                        <ArrowLeft class="size-4" />
                        Back to Membership
                    </Link>
                </Button>
            </div>
        </section>

        <section v-if="fundCycles.length > 0" class="grid gap-4 xl:grid-cols-2">
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

                    <Badge variant="outline">
                        {{ fundCycle.status_label }}
                    </Badge>
                </div>

                <div class="mt-5 grid gap-3 text-sm">
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
                            Slots
                        </div>
                        <p class="mt-2 font-medium text-foreground">
                            {{ fundCycle.allocations_count }} /
                            {{ fundCycle.slots.length }} allocated
                        </p>
                    </div>
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
                    No Open Cycles
                </p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                    No available fund cycles found
                </h2>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Open fund cycles will appear here once they are available
                    for member allocation.
                </p>
            </div>
        </section>
    </div>
</template>
