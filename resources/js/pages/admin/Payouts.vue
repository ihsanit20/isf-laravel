<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
    processed_by: string | null;
    available_balance: number;
    user: {
        id: number;
        name: string | null;
        email: string | null;
        phone: string | null;
    };
};

type Props = {
    filters: { status: string };
    payouts: PayoutItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Payout Requests', href: '/admin/payouts' }],
    },
});

const props = defineProps<Props>();
const selected = ref<PayoutItem | null>(null);
const isPayDialogOpen = ref(false);
const isRejectDialogOpen = ref(false);

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;

const filterBy = (status: string) =>
    router.get('/admin/payouts', status ? { status } : {}, {
        preserveState: true,
    });

const openPay = (payout: PayoutItem) => {
    selected.value = payout;
    isPayDialogOpen.value = true;
};

const openReject = (payout: PayoutItem) => {
    selected.value = payout;
    isRejectDialogOpen.value = true;
};
</script>

<template>
    <Head title="Payout Requests" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <h1 class="text-2xl font-semibold tracking-tight">
                Payout Requests
            </h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Pay members from their available balance. Marking as paid posts
                a journal entry and sends an SMS.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                <Button
                    v-for="status in ['', 'pending', 'paid', 'rejected']"
                    :key="status"
                    size="sm"
                    :variant="
                        props.filters.status === status ? 'default' : 'outline'
                    "
                    @click="filterBy(status)"
                >
                    {{ status || 'all' }}
                </Button>
            </div>
        </section>

        <section
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">User</th>
                        <th class="px-4 py-3 font-medium">Method</th>
                        <th class="px-4 py-3 text-right font-medium">Amount</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Balance now
                        </th>
                        <th class="px-4 py-3 font-medium">Status</th>
                        <th class="px-4 py-3 font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr v-for="payout in props.payouts" :key="payout.id">
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ payout.user.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ payout.user.phone || payout.user.email }} ·
                                {{ payout.requested_at }}
                            </p>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            {{ payout.payment_method_label }}
                            <p class="text-xs">{{ payout.account_details }}</p>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ money(payout.amount) }}
                        </td>
                        <td
                            class="px-4 py-3 text-right tabular-nums"
                            :class="
                                payout.status === 'pending' &&
                                payout.available_balance < payout.amount
                                    ? 'text-destructive'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ money(payout.available_balance) }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge
                                :variant="
                                    payout.status === 'paid'
                                        ? 'default'
                                        : payout.status === 'rejected'
                                          ? 'destructive'
                                          : 'secondary'
                                "
                            >
                                {{ payout.status }}
                            </Badge>
                            <p
                                v-if="payout.status !== 'pending'"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ payout.processed_by }} ·
                                {{ payout.processed_at }}
                                <span v-if="payout.reference_no">
                                    · {{ payout.reference_no }}
                                </span>
                                <span v-if="payout.rejection_reason">
                                    · {{ payout.rejection_reason }}
                                </span>
                            </p>
                        </td>
                        <td class="px-4 py-3">
                            <div
                                v-if="payout.status === 'pending'"
                                class="flex gap-2"
                            >
                                <Button size="sm" @click="openPay(payout)">
                                    Mark paid
                                </Button>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="openReject(payout)"
                                >
                                    Reject
                                </Button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="props.payouts.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No payout requests.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <LedgerEntryDialog
            v-if="selected"
            v-model:isOpen="isPayDialogOpen"
            title="Mark payout as paid"
            :description="`${selected.user.name} · ${money(selected.amount)} via ${selected.payment_method_label}`"
            :action="`/admin/payouts/${selected.id}`"
            method="patch"
            submit-label="Confirm paid"
            :extra="{ status: 'paid' }"
            :fields="[
                {
                    name: 'reference_no',
                    label: 'Transfer reference',
                    type: 'text',
                },
            ]"
        />

        <LedgerEntryDialog
            v-if="selected"
            v-model:isOpen="isRejectDialogOpen"
            title="Reject payout"
            :action="`/admin/payouts/${selected.id}`"
            method="patch"
            submit-label="Reject"
            :extra="{ status: 'rejected' }"
            :fields="[
                { name: 'rejection_reason', label: 'Reason', type: 'textarea' },
            ]"
        />
    </div>
</template>
