<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Banknote, CircleAlert, Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import type {
    EventBankWithdrawal,
    EventDetails,
} from '@/components/admin/event-details/types';
import EventBankWithdrawalFormDialog from '@/components/admin/EventBankWithdrawalFormDialog.vue';
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
import { formatDate, formatMoney } from '@/lib/format';

const props = defineProps<{
    event: EventDetails;
}>();

const isDialogOpen = ref(false);
const editingWithdrawal = ref<EventBankWithdrawal | null>(null);

const openDialog = (withdrawal: EventBankWithdrawal | null = null) => {
    editingWithdrawal.value = withdrawal;
    isDialogOpen.value = true;
};

const deleteWithdrawal = (withdrawal: EventBankWithdrawal) => {
    const label =
        withdrawal.description ||
        withdrawal.reference_no ||
        formatMoney(withdrawal.amount);

    if (!confirm(`"${label}" ব্যাংক উত্তোলন এন্ট্রি মুছে ফেলবেন?`)) {
        return;
    }

    router.delete(
        `/admin/events/${props.event.id}/bank-withdrawals/${withdrawal.id}`,
        { preserveScroll: true },
    );
};
</script>

<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <p class="max-w-2xl text-sm text-muted-foreground">
                Cash taken from the joint bank account for this event. Limited
                by the fund cycle’s money still in the bank.
            </p>
            <Button
                v-if="!props.event.is_finalized"
                size="sm"
                :disabled="
                    props.event.cycle_withdrawal_budget.remaining_amount <= 0
                "
                @click="openDialog()"
            >
                <Plus class="size-4" />
                Record withdrawal
            </Button>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Member capital (this cycle)"
                :value="
                    formatMoney(
                        props.event.cycle_withdrawal_budget.allocated_amount,
                    )
                "
            />
            <StatCard
                label="Deployed (cash, bKash, business)"
                :value="
                    formatMoney(
                        props.event.cycle_withdrawal_budget.withdrawn_amount,
                    )
                "
            />
            <StatCard
                label="Cycle money in bank"
                :value="
                    formatMoney(
                        props.event.cycle_withdrawal_budget.remaining_amount,
                    )
                "
                :value-class="
                    props.event.cycle_withdrawal_budget.remaining_amount <= 0
                        ? 'text-rose-600 dark:text-rose-400'
                        : ''
                "
            />
            <StatCard
                label="Withdrawn for this event"
                :value="
                    formatMoney(props.event.withdrawal_summary.total_amount)
                "
                :hint="`${props.event.withdrawal_summary.entry_count} entries`"
            />
        </div>

        <p
            v-if="props.event.cycle_withdrawal_budget.allocated_amount <= 0"
            class="flex items-center gap-2 text-sm text-rose-600 dark:text-rose-400"
        >
            <CircleAlert class="size-4" />
            Record member allocations for this fund cycle before logging bank
            withdrawals.
        </p>

        <EmptyState
            v-if="props.event.bank_withdrawals.length === 0"
            :icon="Banknote"
            title="No bank withdrawals yet"
        />

        <div v-else class="rounded-xl border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Date</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead>Reference</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Added by</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="withdrawal in props.event.bank_withdrawals"
                        :key="withdrawal.id"
                    >
                        <TableCell class="pl-4 font-medium">
                            {{ formatDate(withdrawal.withdrawal_date) }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ formatMoney(withdrawal.amount) }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ withdrawal.reference_no || '—' }}
                        </TableCell>
                        <TableCell
                            class="max-w-xs whitespace-normal text-muted-foreground"
                        >
                            {{ withdrawal.description || '—' }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ withdrawal.created_by_name || '—' }}
                        </TableCell>
                        <TableCell class="pr-4">
                            <div
                                v-if="!props.event.is_finalized"
                                class="flex justify-end gap-1"
                            >
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="size-8"
                                    @click="openDialog(withdrawal)"
                                >
                                    <Pencil class="size-3.5" />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    class="size-8 text-destructive hover:text-destructive"
                                    @click="deleteWithdrawal(withdrawal)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <EventBankWithdrawalFormDialog
            v-model:isOpen="isDialogOpen"
            :event-id="props.event.id"
            :mode="editingWithdrawal ? 'edit' : 'create'"
            :bank-withdrawal="editingWithdrawal"
            :cycle-withdrawal-budget="props.event.cycle_withdrawal_budget"
        />
    </div>
</template>
