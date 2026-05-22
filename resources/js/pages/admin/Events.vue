<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type EventItem = {
    id: number;
    title: string;
    slug: string;
    status: string;
    status_label: string;
    description: string | null;
    order_open_at: string | null;
    order_close_at: string | null;
    expected_delivery_date: string | null;
    fund_cycle: {
        id: number;
        name: string | null;
        status: string | null;
    };
    created_at: string | null;
};

type Props = {
    events: EventItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Events',
                href: '/admin/events',
            },
        ],
    },
});

defineProps<Props>();
</script>

<template>
    <Head title="Events" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <h1 class="text-2xl font-semibold tracking-tight">All Events</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                View all fund cycle events from one place and open each event
                details page.
            </p>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                >
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Title</th>
                            <th class="px-4 py-3 font-medium">Cycle</th>
                            <th class="px-4 py-3 font-medium">Slug</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Order Window</th>
                            <th class="px-4 py-3 font-medium">Delivery</th>
                            <th class="px-4 py-3 font-medium">Created At</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="event in events" :key="event.id">
                            <td class="px-4 py-3 font-medium">
                                <div>{{ event.title }}</div>
                                <div
                                    v-if="event.description"
                                    class="mt-1 line-clamp-2 max-w-sm text-xs text-muted-foreground"
                                >
                                    {{ event.description }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ event.fund_cycle.name || '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ event.slug }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge variant="outline">{{
                                    event.status_label
                                }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>
                                    Open: {{ event.order_open_at || '-' }}
                                </div>
                                <div>
                                    Close: {{ event.order_close_at || '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ event.expected_delivery_date || '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ event.created_at || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <Button variant="outline" size="sm" as-child>
                                    <Link :href="`/admin/events/${event.id}`">
                                        <Eye class="size-4" />
                                        Details
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="events.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No events found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
