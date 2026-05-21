<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type FundCycleEventPage = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    start_date: string | null;
    lock_date: string | null;
    maturity_date: string | null;
    settlement_date: string | null;
};

type Props = {
    fundCycle: FundCycleEventPage;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Fund Cycles',
                href: '/admin/fund-cycles',
            },
            {
                title: 'Events',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();
</script>

<template>
    <Head :title="`${props.fundCycle.name} - Events`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Fund Cycle Events
                    </p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                        {{ props.fundCycle.name }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Events will be managed under this cycle from this
                        dedicated page.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link
                            :href="`/admin/fund-cycles/${props.fundCycle.id}`"
                        >
                            Back to Details
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link
                            :href="`/admin/fund-cycles/${props.fundCycle.id}/allocations`"
                        >
                            Allocations
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="mt-6 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <div class="text-xs text-muted-foreground">Status</div>
                    <div class="mt-1">
                        <Badge variant="outline">{{
                            props.fundCycle.status_label
                        }}</Badge>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Start</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.start_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Lock</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.lock_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Maturity</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.maturity_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Settlement</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.settlement_date || '-' }}
                    </div>
                </div>
            </div>
        </section>

        <section
            class="rounded-xl border border-dashed border-sidebar-border/70 bg-background px-6 py-10 text-center shadow-sm dark:border-sidebar-border"
        >
            <div class="mx-auto max-w-2xl">
                <h2
                    class="text-lg font-semibold tracking-tight text-foreground"
                >
                    Events page is ready
                </h2>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    This route is now available at
                    /admin/fund-cycles/[id]/events. Event planning and
                    implementation can be added next.
                </p>
            </div>
        </section>
    </div>
</template>
