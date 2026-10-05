<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { isEventLocked } from '@/components/admin/event-details/types';
import type {
    EventDetails,
    EventIncome,
    Option,
} from '@/components/admin/event-details/types';
import InvestmentLedgerPanel from '@/components/admin/InvestmentLedgerPanel.vue';
import type { InvestmentLedger } from '@/components/admin/InvestmentLedgerPanel.vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney, titleCase } from '@/lib/format';

const props = defineProps<{
    event: EventDetails;
    ledger: InvestmentLedger;
    incomeCategories: Option[];
}>();

const emit = defineEmits<{ finalize: []; cancel: [] }>();

const isDialogOpen = ref(false);
const editingIncome = ref<EventIncome | null>(null);

const incomeFields = computed(() => [
    {
        name: 'category',
        label: 'Category',
        type: 'select' as const,
        options: props.incomeCategories,
    },
    {
        name: 'received_via',
        label: 'Received via',
        type: 'select' as const,
        options: [
            { value: 'cash', label: 'Cash (event float)' },
            { value: 'bkash', label: 'bKash wallet' },
            { value: 'bank', label: 'Bank (cycle account)' },
        ],
    },
    { name: 'amount', label: 'Amount (BDT)', type: 'number' as const },
    { name: 'income_date', label: 'Date', type: 'date' as const },
    { name: 'description', label: 'Description', type: 'textarea' as const },
]);

const openDialog = (income: EventIncome | null = null) => {
    editingIncome.value = income;
    isDialogOpen.value = true;
};

const deleteIncome = (income: EventIncome) => {
    if (!confirm(`"${income.category_label}" আয়ের এন্ট্রি মুছে ফেলবেন?`)) {
        return;
    }

    router.delete(`/admin/events/${props.event.id}/incomes/${income.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="grid gap-8">
        <section class="grid gap-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="font-semibold">Other income</h3>
                    <p class="text-sm text-muted-foreground">
                        Income outside order sales, e.g. used boxes sold at a
                        lower price.
                    </p>
                </div>
                <Button
                    v-if="!isEventLocked(props.event)"
                    size="sm"
                    variant="outline"
                    @click="openDialog()"
                >
                    <Plus class="size-4" />
                    Add income
                </Button>
            </div>

            <div class="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12 pl-4">SL</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Category</TableHead>
                            <TableHead>Received via</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead class="pr-4 text-right" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(income, index) in props.event.incomes"
                            :key="income.id"
                        >
                            <TableCell
                                class="pl-4 text-muted-foreground tabular-nums"
                                >{{ index + 1 }}</TableCell
                            >
                            <TableCell>
                                {{ income.income_date }}
                            </TableCell>
                            <TableCell>{{ income.category_label }}</TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ titleCase(income.received_via) }}
                            </TableCell>
                            <TableCell
                                class="max-w-xs whitespace-normal text-muted-foreground"
                            >
                                {{ income.description || '—' }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(income.amount) }}
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
                                        @click="openDialog(income)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-destructive hover:text-destructive"
                                        @click="deleteIncome(income)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableEmpty
                            v-if="props.event.incomes.length === 0"
                            :colspan="7"
                        >
                            No other income.
                        </TableEmpty>
                    </TableBody>
                </Table>
            </div>
        </section>

        <InvestmentLedgerPanel
            :ledger="props.ledger"
            close-label="Finalize event"
            @close="emit('finalize')"
            @cancel="emit('cancel')"
        />

        <LedgerEntryDialog
            v-model:isOpen="isDialogOpen"
            :title="editingIncome ? 'Edit other income' : 'Add other income'"
            description="Income of this event outside order payments."
            :action="
                editingIncome
                    ? `/admin/events/${props.event.id}/incomes/${editingIncome.id}`
                    : `/admin/events/${props.event.id}/incomes`
            "
            :method="editingIncome ? 'put' : 'post'"
            :fields="incomeFields"
            :initial="editingIncome ?? {}"
            :submit-label="editingIncome ? 'Save' : 'Add income'"
        />
    </div>
</template>
