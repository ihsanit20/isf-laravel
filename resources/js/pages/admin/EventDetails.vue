<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type EventDetails = {
    id: number;
    title: string;
    slug: string;
    status: string;
    status_label: string;
    description: string | null;
    order_open_at: string | null;
    order_close_at: string | null;
    expected_delivery_date: string | null;
    created_at: string | null;
    updated_at: string | null;
    fund_cycle: {
        id: number;
        name: string | null;
        status: string | null;
        start_date: string | null;
        lock_date: string | null;
        maturity_date: string | null;
        settlement_date: string | null;
    };
};

type Props = {
    event: EventDetails;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Events',
                href: '/admin/events',
            },
            {
                title: 'Event Details',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();
</script>

<template>
    <Head :title="`${props.event.title} - Event Details`" />

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
                        Event Details
                    </p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                        {{ props.event.title }}
                    </h1>
                    <p
                        v-if="props.event.description"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        {{ props.event.description }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/admin/events">Back to Events</Link>
                    </Button>
                    <Button
                        v-if="props.event.fund_cycle.id"
                        variant="outline"
                        as-child
                    >
                        <Link
                            :href="`/admin/fund-cycles/${props.event.fund_cycle.id}/events`"
                        >
                            Cycle Event Page
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="mt-6 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <div class="text-xs text-muted-foreground">Status</div>
                    <div class="mt-1">
                        <Badge variant="outline">{{
                            props.event.status_label
                        }}</Badge>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Slug</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.slug }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Order Open</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.order_open_at || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Order Close</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.order_close_at || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">
                        Expected Delivery
                    </div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.expected_delivery_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Cycle Name</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.fund_cycle.name || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">
                        Cycle Status
                    </div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.fund_cycle.status || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Created At</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.created_at || '-' }}
                    </div>
                </div>
            </div>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <h2 class="text-lg font-semibold tracking-tight">
                Fund Cycle Timeline
            </h2>
            <div class="mt-4 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <div class="text-xs text-muted-foreground">Start Date</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.fund_cycle.start_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Lock Date</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.fund_cycle.lock_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">
                        Maturity Date
                    </div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.fund_cycle.maturity_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">
                        Settlement Date
                    </div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.event.fund_cycle.settlement_date || '-' }}
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
