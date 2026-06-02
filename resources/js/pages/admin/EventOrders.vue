<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Eye } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type EventSummary = {
    id: number;
    title: string;
    slug: string;
};

type OrderItem = {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string;
    status: string;
    status_label: string;
    total_amount: string;
    total_quantity: number;
    payment_status: string;
    created_at: string | null;
};

type Props = {
    event: EventSummary;
    orders: OrderItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Events',
                href: '/admin/events',
            },
            {
                title: 'Order List',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();
</script>

<template>
    <Head :title="`${props.event.title} - Order List`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="mb-4 flex flex-wrap items-center justify-between gap-3"
            >
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ props.event.title }} - Orders
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Showing only orders for this event.
                    </p>
                </div>
                <Button variant="outline" size="sm" as-child>
                    <Link href="/admin/events">
                        <ArrowLeft class="size-4" />
                        Back to Events
                    </Link>
                </Button>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Order ID</th>
                            <th class="px-4 py-3 font-medium">Customer</th>
                            <th class="px-4 py-3 font-medium">Phone</th>
                            <th class="px-4 py-3 font-medium">Qty</th>
                            <th class="px-4 py-3 font-medium">Total</th>
                            <th class="px-4 py-3 font-medium">Payment</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Created At</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="order in props.orders" :key="order.id">
                            <td class="px-4 py-3 font-medium">
                                {{ order.order_number }}
                            </td>
                            <td class="px-4 py-3">{{ order.customer_name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ order.customer_phone }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ order.total_quantity }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ order.total_amount }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ order.payment_status }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge variant="outline">{{
                                    order.status_label
                                }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ order.created_at || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="`/admin/events/${props.event.id}/orders/${order.id}`"
                                    >
                                        <Eye class="size-4" />
                                        Details
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="props.orders.length === 0">
                            <td
                                colspan="9"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                এই event-এ এখনো কোন order নেই।
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>

