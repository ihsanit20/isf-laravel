<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type EventSummary = {
    id: number;
    title: string;
    slug: string;
};

type PickupPoint = {
    name: string | null;
    area: string | null;
    address: string | null;
    contact_person: string | null;
    phone: string | null;
};

type OrderLine = {
    id: number;
    package_name: string;
    quantity: number;
    quantity_label: string;
    package_price: string;
    line_total: string;
};

type Payment = {
    id: number;
    amount: string;
    payment_method: string | null;
    payment_status: string;
    transaction_reference: string | null;
    paid_at: string | null;
    verified_at: string | null;
};

type StatusHistory = {
    id: number;
    status: string;
    note: string | null;
    changed_at: string | null;
    changed_by: string | null;
};

type OrderDetails = {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string;
    customer_address: string | null;
    status: string;
    status_label: string;
    total_amount: string;
    advance_amount: string;
    due_amount: string;
    created_at: string | null;
    confirmed_at: string | null;
    pickup_point: PickupPoint | null;
    items: OrderLine[];
    payments: Payment[];
    status_histories: StatusHistory[];
};

type Props = {
    event: EventSummary;
    order: OrderDetails;
};

defineOptions({
    layout: (props: Props) => ({
        breadcrumbs: [
            {
                title: 'Events',
                href: '/admin/events',
            },
            {
                title: 'Order List',
                href: `/admin/events/${props.event.id}/orders`,
            },
            {
                title: 'Order Details',
                href: '#',
            },
        ],
    }),
});

const props = defineProps<Props>();
</script>

<template>
    <Head :title="`${props.order.order_number} - Order Details`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ props.order.order_number }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Event: {{ props.event.title }}
                    </p>
                </div>
                <Button variant="outline" size="sm" as-child>
                    <Link :href="`/admin/events/${props.event.id}/orders`">
                        <ArrowLeft class="size-4" />
                        Back to Order List
                    </Link>
                </Button>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-emerald-300/60 bg-emerald-50 p-5">
                <div class="text-xs font-semibold tracking-wide text-emerald-700 uppercase">
                    Total Amount
                </div>
                <div class="mt-2 text-2xl font-bold text-emerald-800">
                    {{ props.order.total_amount }}
                </div>
            </div>
            <div class="rounded-xl border border-blue-300/60 bg-blue-50 p-5">
                <div class="text-xs font-semibold tracking-wide text-blue-700 uppercase">
                    Advance Paid/Required
                </div>
                <div class="mt-2 text-2xl font-bold text-blue-800">
                    {{ props.order.advance_amount }}
                </div>
            </div>
            <div class="rounded-xl border border-amber-300/60 bg-amber-50 p-5">
                <div class="text-xs font-semibold tracking-wide text-amber-700 uppercase">
                    Due Amount
                </div>
                <div class="mt-2 text-2xl font-bold text-amber-800">
                    {{ props.order.due_amount }}
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-sidebar-border/70 bg-background p-5">
                <h2 class="text-base font-semibold">Order Info</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Status</span>
                        <Badge variant="outline">{{ props.order.status_label }}</Badge>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Customer</span>
                        <span>{{ props.order.customer_name }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Phone</span>
                        <span>{{ props.order.customer_phone }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Address</span>
                        <span>{{ props.order.customer_address || '-' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Total</span>
                        <span>{{ props.order.total_amount }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Advance</span>
                        <span>{{ props.order.advance_amount }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Due</span>
                        <span>{{ props.order.due_amount }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Created At</span>
                        <span>{{ props.order.created_at || '-' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Confirmed At</span>
                        <span>{{ props.order.confirmed_at || '-' }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-background p-5">
                <h2 class="text-base font-semibold">Pickup Point</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <template v-if="props.order.pickup_point">
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Name</span>
                            <span>{{ props.order.pickup_point.name || '-' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Area</span>
                            <span>{{ props.order.pickup_point.area || '-' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Address</span>
                            <span>{{ props.order.pickup_point.address || '-' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Contact</span>
                            <span>{{ props.order.pickup_point.contact_person || '-' }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Phone</span>
                            <span>{{ props.order.pickup_point.phone || '-' }}</span>
                        </div>
                    </template>
                    <p v-else class="text-muted-foreground">
                        Pickup point information is not available.
                    </p>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm"
        >
            <div class="border-b border-sidebar-border/70 px-4 py-3">
                <h2 class="text-base font-semibold">Order Items</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Package</th>
                            <th class="px-4 py-3 font-medium">Quantity</th>
                            <th class="px-4 py-3 font-medium">Package Price</th>
                            <th class="px-4 py-3 font-medium">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="item in props.order.items" :key="item.id">
                            <td class="px-4 py-3">{{ item.package_name }}</td>
                            <td class="px-4 py-3">{{ item.quantity_label }}</td>
                            <td class="px-4 py-3">{{ item.package_price }}</td>
                            <td class="px-4 py-3">{{ item.line_total }}</td>
                        </tr>
                        <tr v-if="props.order.items.length === 0">
                            <td colspan="4" class="px-4 py-6 text-center text-muted-foreground">
                                No order items found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-sidebar-border/70 bg-background">
                <div class="border-b border-sidebar-border/70 px-4 py-3">
                    <h2 class="text-base font-semibold">Payments</h2>
                </div>
                <div class="p-4">
                    <div v-if="props.order.payments.length > 0" class="space-y-3 text-sm">
                        <div
                            v-for="payment in props.order.payments"
                            :key="payment.id"
                            class="rounded-md border border-sidebar-border/70 p-3"
                        >
                            <div class="flex justify-between gap-3">
                                <span class="text-muted-foreground">Amount</span>
                                <span>{{ payment.amount }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-muted-foreground">Method</span>
                                <span>{{ payment.payment_method || '-' }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-muted-foreground">Status</span>
                                <span>{{ payment.payment_status }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-muted-foreground">Txn Ref</span>
                                <span>{{ payment.transaction_reference || '-' }}</span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No payment records found.
                    </p>
                </div>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-background">
                <div class="border-b border-sidebar-border/70 px-4 py-3">
                    <h2 class="text-base font-semibold">Status History</h2>
                </div>
                <div class="p-4">
                    <div
                        v-if="props.order.status_histories.length > 0"
                        class="space-y-3 text-sm"
                    >
                        <div
                            v-for="history in props.order.status_histories"
                            :key="history.id"
                            class="rounded-md border border-sidebar-border/70 p-3"
                        >
                            <div class="flex justify-between gap-3">
                                <span class="text-muted-foreground">Status</span>
                                <span>{{ history.status }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-muted-foreground">Changed At</span>
                                <span>{{ history.changed_at || '-' }}</span>
                            </div>
                            <div class="flex justify-between gap-3">
                                <span class="text-muted-foreground">Changed By</span>
                                <span>{{ history.changed_by || 'System' }}</span>
                            </div>
                            <div class="mt-2 text-muted-foreground" v-if="history.note">
                                {{ history.note }}
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No status history found.
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>

