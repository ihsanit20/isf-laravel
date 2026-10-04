<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    CircleAlert,
    FileText,
    Pencil,
    Plus,
    Trash2,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import type {
    EventDetails,
    EventExpense,
    Option,
} from '@/components/admin/event-details/types';
import EventExpenseFormDialog from '@/components/admin/EventExpenseFormDialog.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
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
import { cn } from '@/lib/utils';

const props = defineProps<{
    event: EventDetails;
    expenseCategories: Option[];
}>();

const isDialogOpen = ref(false);
const editingExpense = ref<EventExpense | null>(null);

const float = computed(() => props.event.float_summary);

const floatLines = computed(() =>
    [
        {
            label: 'Withdrawn from bank',
            amount: float.value.withdrawn_from_bank,
            sign: '+',
        },
        {
            label: 'Cash received',
            amount: float.value.cash_received,
            sign: '+',
        },
        {
            label: 'Cash expenses',
            amount: float.value.logged_expenses,
            sign: '−',
        },
        { label: 'Cash refunds', amount: float.value.cash_refunded, sign: '−' },
        {
            label: 'Deposited to bank',
            amount: float.value.deposited_to_bank,
            sign: '−',
        },
        { label: 'Other', amount: float.value.other_movements, sign: '' },
    ].filter(
        (line) =>
            line.amount !== 0 ||
            !['Cash refunds', 'Other'].includes(line.label),
    ),
);

const openDialog = (expense: EventExpense | null = null) => {
    editingExpense.value = expense;
    isDialogOpen.value = true;
};

const deleteExpense = (expense: EventExpense) => {
    const label = expense.description || expense.category_label;

    if (!confirm(`"${label}" খরচের এন্ট্রি মুছে ফেলবেন?`)) {
        return;
    }

    router.delete(`/admin/events/${props.event.id}/expenses/${expense.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="grid gap-4">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <p class="max-w-2xl text-sm text-muted-foreground">
                Expenses paid from the event’s cash float or bKash. They count
                against the event result, not the bank balance directly.
            </p>
            <Button
                v-if="!props.event.is_finalized"
                size="sm"
                @click="openDialog()"
            >
                <Plus class="size-4" />
                Add cost
            </Button>
        </div>

        <div class="grid gap-4 lg:grid-cols-[1fr_2fr]">
            <div
                :class="
                    cn(
                        'h-fit rounded-xl border p-4',
                        float.is_over_logged &&
                            'border-rose-300 bg-rose-50 dark:border-rose-900 dark:bg-rose-950',
                    )
                "
            >
                <p class="text-sm font-medium">Event cash (from the journal)</p>
                <dl class="mt-3 grid gap-1.5 text-sm">
                    <div
                        v-for="line in floatLines"
                        :key="line.label"
                        class="flex justify-between gap-2"
                    >
                        <dt class="text-muted-foreground">
                            {{ line.sign }} {{ line.label }}
                        </dt>
                        <dd class="tabular-nums">
                            {{ formatMoney(line.amount) }}
                        </dd>
                    </div>
                    <div
                        class="flex justify-between gap-2 border-t pt-1.5 font-medium"
                    >
                        <dt>In hand</dt>
                        <dd
                            :class="
                                cn(
                                    'tabular-nums',
                                    float.is_over_logged &&
                                        'text-rose-600 dark:text-rose-400',
                                )
                            "
                        >
                            {{ formatMoney(float.remaining_float) }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="float.is_over_logged"
                    class="mt-3 flex gap-2 text-xs text-rose-700 dark:text-rose-300"
                >
                    <CircleAlert class="size-4 shrink-0" />
                    More cash is recorded going out than coming in. Check the
                    expenses, deposits and withdrawals.
                </p>
            </div>

            <div class="grid h-fit gap-3">
                <p class="text-sm">
                    <span class="font-medium">
                        {{
                            formatMoney(
                                props.event.expense_summary.total_amount,
                            )
                        }}
                    </span>
                    <span class="text-muted-foreground">
                        in {{ props.event.expense_summary.entry_count }}
                        {{
                            props.event.expense_summary.entry_count === 1
                                ? 'entry'
                                : 'entries'
                        }}
                    </span>
                </p>

                <EmptyState
                    v-if="props.event.expenses.length === 0"
                    :icon="Wallet"
                    title="No costs yet"
                />

                <div v-else class="rounded-xl border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="pl-4">Date</TableHead>
                                <TableHead>Category</TableHead>
                                <TableHead class="text-right">Amount</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead>Added by</TableHead>
                                <TableHead class="pr-4 text-right" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="expense in props.event.expenses"
                                :key="expense.id"
                            >
                                <TableCell class="pl-4 font-medium">
                                    {{ formatDate(expense.expense_date) }}
                                </TableCell>
                                <TableCell>
                                    {{ expense.category_label }}
                                </TableCell>
                                <TableCell class="text-right tabular-nums">
                                    {{ formatMoney(expense.amount) }}
                                </TableCell>
                                <TableCell
                                    class="max-w-xs whitespace-normal text-muted-foreground"
                                >
                                    {{ expense.description || '—' }}
                                    <a
                                        v-if="expense.receipt_url"
                                        :href="expense.receipt_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="mt-1 flex items-center gap-1 text-xs text-foreground underline underline-offset-4"
                                    >
                                        <FileText class="size-3" />
                                        Receipt
                                    </a>
                                </TableCell>
                                <TableCell class="text-muted-foreground">
                                    {{ expense.created_by_name || '—' }}
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
                                            @click="openDialog(expense)"
                                        >
                                            <Pencil class="size-3.5" />
                                        </Button>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            class="size-8 text-destructive hover:text-destructive"
                                            @click="deleteExpense(expense)"
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
        </div>

        <EventExpenseFormDialog
            v-model:isOpen="isDialogOpen"
            :event-id="props.event.id"
            :mode="editingExpense ? 'edit' : 'create'"
            :expense-categories="props.expenseCategories"
            :event-expense="editingExpense"
        />
    </div>
</template>
