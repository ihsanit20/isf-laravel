<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatMoney, titleCase } from '@/lib/format';

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
        breadcrumbs: [{ title: 'Payouts', href: '/admin/payouts' }],
    },
});

const props = defineProps<Props>();
const selected = ref<PayoutItem | null>(null);
const isPayDialogOpen = ref(false);
const isRejectDialogOpen = ref(false);

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
    <Head title="Payouts" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Payouts"
            description="Withdrawal requests from members’ available balance. Marking one paid posts a journal entry and sends an SMS."
        />

        <Tabs :model-value="props.filters.status || 'all'">
            <TabsList>
                <TabsTrigger
                    v-for="status in ['all', 'pending', 'paid', 'rejected']"
                    :key="status"
                    :value="status"
                    @click="filterBy(status === 'all' ? '' : status)"
                >
                    {{ titleCase(status) }}
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12 pl-4">SL</TableHead>
                        <TableHead>User</TableHead>
                        <TableHead>Receive via</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead class="text-right">Balance now</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(payout, index) in props.payouts"
                        :key="payout.id"
                    >
                        <TableCell
                            class="pl-4 text-muted-foreground tabular-nums"
                            >{{ index + 1 }}</TableCell
                        >
                        <TableCell>
                            <p class="font-medium">{{ payout.user.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ payout.user.phone || payout.user.email }} ·
                                {{ payout.requested_at }}
                            </p>
                        </TableCell>
                        <TableCell>
                            {{ payout.payment_method_label }}
                            <p class="text-xs text-muted-foreground">
                                {{ payout.account_details }}
                            </p>
                        </TableCell>
                        <TableCell class="text-right font-medium tabular-nums">
                            {{ formatMoney(payout.amount) }}
                        </TableCell>
                        <TableCell
                            class="text-right tabular-nums"
                            :class="
                                payout.status === 'pending' &&
                                payout.available_balance < payout.amount
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : 'text-muted-foreground'
                            "
                        >
                            {{ formatMoney(payout.available_balance) }}
                        </TableCell>
                        <TableCell class="max-w-xs whitespace-normal">
                            <StatusBadge :status="payout.status" />
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
                        </TableCell>
                        <TableCell class="pr-4">
                            <div
                                v-if="payout.status === 'pending'"
                                class="flex justify-end gap-2"
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
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="props.payouts.length === 0" :colspan="7">
                        No payout requests.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

        <LedgerEntryDialog
            v-if="selected"
            v-model:isOpen="isPayDialogOpen"
            title="Mark payout as paid"
            :description="`${selected.user.name} · ${formatMoney(selected.amount)} via ${selected.payment_method_label}`"
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
