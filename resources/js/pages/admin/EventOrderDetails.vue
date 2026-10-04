<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Banknote, Printer, RefreshCw } from 'lucide-vue-next';
import { ref } from 'vue';
import EventOrderRecordPaymentDialog from '@/components/admin/EventOrderRecordPaymentDialog.vue';
import EventOrderStatusUpdateDialog from '@/components/admin/EventOrderStatusUpdateDialog.vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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
    payment_type: string | null;
    payment_type_label: string;
    payment_method: string | null;
    payment_status: string;
    transaction_reference: string | null;
    note: string | null;
    paid_at: string | null;
    verified_at: string | null;
    verified_by: string | null;
    can_verify: boolean;
};

type StatusHistory = {
    id: number;
    status: string;
    note: string | null;
    changed_at: string | null;
    changed_by: string | null;
};

type StatusOption = {
    value: string;
    label: string;
};

type PaymentMethodOption = {
    value: string;
    label: string;
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
    is_full_payment: boolean;
    due_amount: string;
    verified_paid_amount: string;
    can_record_payment: boolean;
    can_update_status: boolean;
    created_at: string | null;
    confirmed_at: string | null;
    pickup_point: PickupPoint | null;
    items: OrderLine[];
    payments: Payment[];
    refunds: {
        id: number;
        amount: string;
        method: string;
        reference_no: string | null;
        note: string | null;
        refunded_at: string | null;
    }[];
    refundable_amount: number;
    can_refund: boolean;
    status_histories: StatusHistory[];
};

type Props = {
    event: EventSummary;
    order: OrderDetails;
    statusOptions: StatusOption[];
    paymentMethodOptions: PaymentMethodOption[];
};

defineOptions({
    layout: (props: Props) => ({
        breadcrumbs: [
            { title: 'Events', href: '/admin/events' },
            {
                title: 'Order List',
                href: `/admin/events/${props.event.id}/orders`,
            },
            { title: 'Order Details', href: '#' },
        ],
    }),
});

const props = defineProps<Props>();

const baseUrl = `/admin/events/${props.event.id}/orders/${props.order.id}`;
const customerReceiptPrintUrl = `${baseUrl}/print/receipt`;

const isStatusDialogOpen = ref(false);
const isPaymentDialogOpen = ref(false);
const isRefundDialogOpen = ref(false);

const rejectingPaymentId = ref<number | null>(null);

const rejectForm = useForm({
    status: 'failed' as const,
    rejection_reason: '',
});

const verifyPayment = (paymentId: number) => {
    useForm({ status: 'verified' }).patch(`${baseUrl}/payments/${paymentId}`, {
        preserveScroll: true,
    });
};

const openReject = (paymentId: number) => {
    rejectingPaymentId.value = paymentId;
    rejectForm.reset();
};

const submitReject = (paymentId: number) => {
    rejectForm.patch(`${baseUrl}/payments/${paymentId}`, {
        preserveScroll: true,
        onSuccess: () => {
            rejectingPaymentId.value = null;
            rejectForm.reset();
        },
    });
};
</script>

<template>
    <Head :title="`${props.order.order_number} - Order Details`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="props.order.order_number"
            :description="`Event: ${props.event.title}`"
        >
            <template #actions>
                <Button
                    v-if="props.order.can_update_status"
                    variant="outline"
                    @click="isStatusDialogOpen = true"
                >
                    <RefreshCw class="size-4" />
                    Update status
                </Button>
                <Button
                    v-if="props.order.can_record_payment"
                    variant="outline"
                    @click="isPaymentDialogOpen = true"
                >
                    <Banknote class="size-4" />
                    Record payment
                </Button>
                <Button
                    v-if="
                        props.order.can_refund &&
                        props.order.refundable_amount > 0
                    "
                    variant="outline"
                    @click="isRefundDialogOpen = true"
                >
                    <Banknote class="size-4" />
                    Refund
                </Button>
                <Button variant="outline" as-child>
                    <a
                        :href="customerReceiptPrintUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Printer class="size-4" />
                        কাস্টমার কপি
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="`/admin/events/${props.event.id}/orders`">
                        <ArrowLeft class="size-4" />
                        Orders
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard label="Total" :value="props.order.total_amount" />
            <StatCard
                :label="
                    props.order.is_full_payment
                        ? 'Full payment'
                        : 'Advance required'
                "
                :value="props.order.advance_amount"
            />
            <StatCard
                label="Verified paid"
                :value="props.order.verified_paid_amount"
                value-class="text-emerald-600 dark:text-emerald-400"
            />
            <StatCard
                label="Due"
                :value="props.order.due_amount"
                value-class="text-amber-600 dark:text-amber-400"
            />
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <h2 class="text-base font-semibold">Order Info</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Status</span>
                        <StatusBadge
                            :status="props.order.status"
                            :label="props.order.status_label"
                        />
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
                        <span class="text-muted-foreground">Created</span>
                        <span>{{ props.order.created_at || '-' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Confirmed</span>
                        <span>{{ props.order.confirmed_at || '-' }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-xs">
                <h2 class="text-base font-semibold">Pickup Point</h2>
                <div class="mt-3 space-y-2 text-sm">
                    <template v-if="props.order.pickup_point">
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Name</span>
                            <span>{{
                                props.order.pickup_point.name || '-'
                            }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Contact</span>
                            <span>{{
                                props.order.pickup_point.contact_person || '-'
                            }}</span>
                        </div>
                        <div class="flex justify-between gap-3">
                            <span class="text-muted-foreground">Phone</span>
                            <span>{{
                                props.order.pickup_point.phone || '-'
                            }}</span>
                        </div>
                    </template>
                    <p v-else class="text-muted-foreground">Not set</p>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">Order Items</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Package</th>
                            <th class="px-4 py-3 font-medium">Quantity</th>
                            <th class="px-4 py-3 font-medium">Price</th>
                            <th class="px-4 py-3 font-medium">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in props.order.items" :key="item.id">
                            <td class="px-4 py-3">{{ item.package_name }}</td>
                            <td class="px-4 py-3">{{ item.quantity_label }}</td>
                            <td class="px-4 py-3">{{ item.package_price }}</td>
                            <td class="px-4 py-3">{{ item.line_total }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border bg-card shadow-xs">
                <div class="border-b px-4 py-3">
                    <h2 class="text-base font-semibold">Payments</h2>
                </div>
                <div class="space-y-3 p-4">
                    <div
                        v-for="payment in props.order.payments"
                        :key="payment.id"
                        class="rounded-md border p-3 text-sm"
                    >
                        <div class="flex justify-between gap-3 font-medium">
                            <span class="flex items-center gap-2">
                                {{ payment.payment_type_label }}
                                <StatusBadge :status="payment.payment_status" />
                            </span>
                            <span>{{ payment.amount }}</span>
                        </div>
                        <div class="mt-2 space-y-1 text-muted-foreground">
                            <div>{{ payment.payment_method || '—' }}</div>
                            <div v-if="payment.transaction_reference">
                                Ref: {{ payment.transaction_reference }}
                            </div>
                            <div v-if="payment.verified_by">
                                Verified by {{ payment.verified_by }}
                            </div>
                            <div v-if="payment.note">{{ payment.note }}</div>
                        </div>
                        <div
                            v-if="payment.can_verify"
                            class="mt-3 flex flex-wrap gap-2"
                        >
                            <Button
                                size="sm"
                                @click="verifyPayment(payment.id)"
                            >
                                Verify
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                @click="openReject(payment.id)"
                            >
                                Reject
                            </Button>
                        </div>
                        <form
                            v-if="rejectingPaymentId === payment.id"
                            class="mt-3 space-y-2"
                            @submit.prevent="submitReject(payment.id)"
                        >
                            <Input
                                v-model="rejectForm.rejection_reason"
                                placeholder="Rejection reason"
                            />
                            <Button
                                type="submit"
                                size="sm"
                                variant="destructive"
                                :disabled="rejectForm.processing"
                            >
                                Confirm reject
                            </Button>
                        </form>
                    </div>
                    <p
                        v-if="props.order.payments.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        No payments yet.
                    </p>
                </div>
            </div>

            <div class="rounded-xl border bg-card shadow-xs">
                <div class="border-b px-4 py-3">
                    <h2 class="text-base font-semibold">Status History</h2>
                </div>
                <div class="space-y-3 p-4">
                    <div
                        v-for="history in props.order.status_histories"
                        :key="history.id"
                        class="rounded-md border p-3 text-sm"
                    >
                        <div class="font-medium">{{ history.status }}</div>
                        <div class="mt-1 text-muted-foreground">
                            {{ history.changed_at || '-' }} ·
                            {{ history.changed_by || 'System' }}
                        </div>
                        <p
                            v-if="history.note"
                            class="mt-2 text-muted-foreground"
                        >
                            {{ history.note }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <EventOrderStatusUpdateDialog
            v-model:is-open="isStatusDialogOpen"
            :event-id="props.event.id"
            :order="props.order"
            :status-options="props.statusOptions"
        />
        <section
            v-if="props.order.refunds.length > 0"
            class="rounded-xl border bg-card shadow-xs"
        >
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-semibold">Refunds</h2>
            </div>
            <div class="space-y-2 p-4 text-sm">
                <div
                    v-for="refund in props.order.refunds"
                    :key="refund.id"
                    class="flex flex-wrap justify-between gap-2 rounded-md border p-3"
                >
                    <span>
                        {{ refund.refunded_at }} · {{ refund.method }}
                        <span v-if="refund.reference_no">
                            · {{ refund.reference_no }}
                        </span>
                    </span>
                    <span class="font-medium tabular-nums">
                        {{ refund.amount }} BDT
                    </span>
                    <p v-if="refund.note" class="w-full text-muted-foreground">
                        {{ refund.note }}
                    </p>
                </div>
            </div>
        </section>

        <LedgerEntryDialog
            v-model:isOpen="isRefundDialogOpen"
            title="Refund customer"
            :description="`Up to ${props.order.refundable_amount} BDT can be refunded. Posted as a sales refund of this event.`"
            :action="`${baseUrl}/refunds`"
            submit-label="Record refund"
            :fields="[
                { name: 'amount', label: 'Amount (BDT)', type: 'number' },
                {
                    name: 'method',
                    label: 'Paid back via',
                    type: 'select',
                    options: [
                        { value: 'bkash', label: 'bKash' },
                        { value: 'cash', label: 'Cash (event float)' },
                        { value: 'bank', label: 'Bank (cycle account)' },
                    ],
                },
                { name: 'refunded_at', label: 'Date', type: 'date' },
                { name: 'reference_no', label: 'Reference', type: 'text' },
                { name: 'note', label: 'Note', type: 'textarea' },
            ]"
        />

        <EventOrderRecordPaymentDialog
            v-model:is-open="isPaymentDialogOpen"
            :event-id="props.event.id"
            :order="props.order"
            :payment-method-options="props.paymentMethodOptions"
        />
    </div>
</template>
