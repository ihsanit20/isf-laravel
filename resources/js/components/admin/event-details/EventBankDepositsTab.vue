<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Landmark, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { isEventLocked } from '@/components/admin/event-details/types';
import type {
    EventBankDeposit,
    EventDetails,
} from '@/components/admin/event-details/types';
import EventBankDepositFormDialog from '@/components/admin/EventBankDepositFormDialog.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatMoney, titleCase } from '@/lib/format';

const props = defineProps<{
    event: EventDetails;
}>();

const isDialogOpen = ref(false);
const editingDeposit = ref<EventBankDeposit | null>(null);

const openDialog = (deposit: EventBankDeposit | null = null) => {
    editingDeposit.value = deposit;
    isDialogOpen.value = true;
};

const deleteDeposit = (deposit: EventBankDeposit) => {
    const label =
        deposit.description ||
        deposit.reference_no ||
        formatMoney(deposit.amount);

    if (!confirm(`"${label}" ব্যাংক জমা এন্ট্রি মুছে ফেলবেন?`)) {
        return;
    }

    router.delete(
        `/admin/events/${props.event.id}/bank-deposits/${deposit.id}`,
        { preserveScroll: true },
    );
};
</script>

<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <p class="max-w-2xl text-sm text-muted-foreground">
                Cash or bKash money returned to the joint bank account from this
                event. Record bKash settlements with source "bKash", and the
                bKash fee as an event cost paid from bKash.
            </p>
            <Button
                v-if="!isEventLocked(props.event)"
                size="sm"
                @click="openDialog()"
            >
                <Plus class="size-4" />
                Record deposit
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Verified customer payments"
                :value="
                    formatMoney(
                        props.event.bank_deposit_reconciliation
                            .verified_customer_payments,
                    )
                "
            />
            <StatCard
                label="Deposited to bank"
                :value="
                    formatMoney(
                        props.event.bank_deposit_reconciliation
                            .deposited_to_bank,
                    )
                "
                :hint="`${props.event.bank_deposit_summary.entry_count} entries`"
            />
            <StatCard
                label="Not yet in bank"
                :value="
                    formatMoney(
                        props.event.bank_deposit_reconciliation
                            .not_yet_deposited,
                    )
                "
                :value-class="
                    props.event.bank_deposit_reconciliation.not_yet_deposited >
                    0
                        ? 'text-amber-600 dark:text-amber-400'
                        : ''
                "
                :hint="`Cash ${formatMoney(props.event.bank_deposit_reconciliation.cash_in_hand)} · bKash ${formatMoney(props.event.bank_deposit_reconciliation.bkash_wallet)}`"
            />
            <StatCard
                label="Total deposited (entries)"
                :value="
                    formatMoney(props.event.bank_deposit_summary.total_amount)
                "
            />
        </div>

        <EmptyState
            v-if="props.event.bank_deposits.length === 0"
            :icon="Landmark"
            title="No bank deposits yet"
        />

        <div v-else class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Date</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead>Source</TableHead>
                        <TableHead>Reference</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Added by</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="deposit in props.event.bank_deposits"
                        :key="deposit.id"
                    >
                        <TableCell class="pl-4 font-medium">
                            {{ formatDate(deposit.deposit_date) }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ formatMoney(deposit.amount) }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ titleCase(deposit.source) }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ deposit.reference_no || '—' }}
                        </TableCell>
                        <TableCell
                            class="max-w-xs whitespace-normal text-muted-foreground"
                        >
                            {{ deposit.description || '—' }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ deposit.created_by_name || '—' }}
                        </TableCell>
                        <TableCell class="pr-4">
                            <div
                                v-if="!isEventLocked(props.event)"
                                class="flex justify-end gap-1"
                            >
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="size-8"
                                    @click="openDialog(deposit)"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="size-8 text-destructive hover:text-destructive"
                                    @click="deleteDeposit(deposit)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <EventBankDepositFormDialog
            v-model:isOpen="isDialogOpen"
            :event-id="props.event.id"
            :mode="editingDeposit ? 'edit' : 'create'"
            :bank-deposit="editingDeposit"
        />
    </div>
</template>
