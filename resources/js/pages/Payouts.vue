<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type PayoutItem = {
    id: number;
    amount: number;
    payment_method_label: string;
    account_details: string | null;
    notes: string | null;
    status: 'pending' | 'paid' | 'rejected';
    reference_no: string | null;
    rejection_reason: string | null;
    requested_at: string | null;
    processed_at: string | null;
};

type Props = {
    summary: {
        available_balance: number;
        pending_amount: number;
        requestable_amount: number;
    };
    paymentMethods: { value: string; label: string }[];
    payouts: PayoutItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My Payouts', href: '/my-payouts' }],
    },
});

const props = defineProps<Props>();
const isRequestDialogOpen = ref(false);

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;

const statusVariant = (status: PayoutItem['status']) =>
    status === 'paid'
        ? 'default'
        : status === 'rejected'
          ? 'destructive'
          : 'secondary';
</script>

<template>
    <Head title="My Payouts" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        My Payouts
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Withdraw money from your available balance (deposits
                        plus returned capital and profit, minus fees and
                        allocations).
                    </p>
                </div>
                <Button
                    class="shrink-0"
                    :disabled="props.summary.requestable_amount <= 0"
                    @click="isRequestDialogOpen = true"
                >
                    <Plus class="size-4" />
                    Request payout
                </Button>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <p class="text-xs text-muted-foreground">
                        Available balance
                    </p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ money(props.summary.available_balance) }}
                    </p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <p class="text-xs text-muted-foreground">
                        Pending requests
                    </p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ money(props.summary.pending_amount) }}
                    </p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <p class="text-xs text-muted-foreground">You can request</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ money(props.summary.requestable_amount) }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Requested</th>
                        <th class="px-4 py-3 font-medium">Method</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Details</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr v-for="payout in props.payouts" :key="payout.id">
                        <td class="px-4 py-3">{{ payout.requested_at }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ payout.payment_method_label }}
                            <p class="text-xs">{{ payout.account_details }}</p>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ money(payout.amount) }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="statusVariant(payout.status)">
                                {{ payout.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <span v-if="payout.status === 'paid'">
                                {{ payout.processed_at }}
                                <span v-if="payout.reference_no">
                                    · Ref {{ payout.reference_no }}
                                </span>
                            </span>
                            <span v-else-if="payout.status === 'rejected'">
                                {{ payout.rejection_reason }}
                            </span>
                            <span v-else>{{ payout.notes || '-' }}</span>
                        </td>
                    </tr>
                    <tr v-if="props.payouts.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No payout requests yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <LedgerEntryDialog
            v-model:isOpen="isRequestDialogOpen"
            title="Request payout"
            :description="`You can request up to ${money(props.summary.requestable_amount)}.`"
            action="/my-payouts"
            submit-label="Send request"
            :fields="[
                { name: 'amount', label: 'Amount (BDT)', type: 'number' },
                {
                    name: 'payment_method',
                    label: 'Receive via',
                    type: 'select',
                    options: props.paymentMethods,
                },
                {
                    name: 'account_details',
                    label: 'Account / number',
                    type: 'text',
                    placeholder: 'Bank account or mobile number',
                },
                { name: 'notes', label: 'Notes', type: 'textarea' },
            ]"
        />
    </div>
</template>
