<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CreditCard } from 'lucide-vue-next';
import type { EventDetails } from '@/components/admin/event-details/types';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    event: EventDetails;
}>();
</script>

<template>
    <div class="grid gap-4">
        <p class="text-sm text-muted-foreground">
            Advance and due payments recorded for orders on this event. Verify
            or record new payments from the order page.
        </p>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Verified"
                :value="
                    formatMoney(props.event.payment_summary.verified_amount)
                "
                :hint="`${props.event.payment_summary.verified_count} payments`"
                value-class="text-emerald-600 dark:text-emerald-400"
            />
            <StatCard
                label="Entries"
                :value="props.event.payment_summary.entry_count"
            />
            <StatCard
                label="Pending"
                :value="props.event.payment_summary.pending_count"
            />
            <StatCard
                label="Failed"
                :value="props.event.payment_summary.failed_count"
            />
        </div>

        <EmptyState
            v-if="props.event.payments.length === 0"
            :icon="CreditCard"
            title="No payments yet"
        />

        <div v-else class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Date</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead>Order</TableHead>
                        <TableHead>Customer</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="pr-4">Reference</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="payment in props.event.payments"
                        :key="payment.id"
                    >
                        <TableCell class="pl-4">
                            {{ payment.paid_at || payment.verified_at || '—' }}
                        </TableCell>
                        <TableCell class="text-right font-medium tabular-nums">
                            {{ formatMoney(payment.amount) }}
                        </TableCell>
                        <TableCell>
                            <Link
                                :href="`/admin/events/${props.event.id}/orders/${payment.order_id}`"
                                class="font-medium hover:underline"
                            >
                                {{ payment.order_number || '—' }}
                            </Link>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ payment.customer_name || '—' }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ payment.payment_type_label }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ payment.payment_method || '—' }}
                        </TableCell>
                        <TableCell>
                            <StatusBadge
                                :status="payment.payment_status"
                                :label="payment.payment_status_label"
                            />
                        </TableCell>
                        <TableCell
                            class="max-w-xs pr-4 whitespace-normal text-muted-foreground"
                        >
                            {{ payment.transaction_reference || '—' }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    </div>
</template>
