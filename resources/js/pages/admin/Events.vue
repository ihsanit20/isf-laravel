<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, ListOrdered } from 'lucide-vue-next';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';

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
    orders_count: number;
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

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Events"
            description="Every event across all fund cycles. Open an event to manage packages, orders, money and finalization."
        />

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Event</TableHead>
                        <TableHead>Cycle</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Order window</TableHead>
                        <TableHead>Delivery</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="event in events" :key="event.id">
                        <TableCell class="max-w-sm pl-4 whitespace-normal">
                            <Link
                                :href="`/admin/events/${event.id}`"
                                class="font-medium hover:underline"
                            >
                                {{ event.title }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                /{{ event.slug }}
                            </p>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ event.fund_cycle.name || '—' }}
                        </TableCell>
                        <TableCell>
                            <StatusBadge
                                :status="event.status"
                                :label="event.status_label"
                            />
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground">
                            <p>Open {{ event.order_open_at || '—' }}</p>
                            <p>Close {{ event.order_close_at || '—' }}</p>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ event.expected_delivery_date || '—' }}
                        </TableCell>
                        <TableCell class="pr-4">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="`/admin/events/${event.id}/orders`"
                                    >
                                        <ListOrdered class="size-4" />
                                        Orders ({{ event.orders_count }})
                                    </Link>
                                </Button>
                                <Button size="sm" as-child>
                                    <Link :href="`/admin/events/${event.id}`">
                                        <Eye class="size-4" />
                                        Details
                                    </Link>
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="events.length === 0" :colspan="6">
                        No events yet. Create one from a fund cycle.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>
    </div>
</template>
