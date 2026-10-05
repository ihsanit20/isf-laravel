<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText, Plus, SquarePen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import GeneralExpenseFormDialog from '@/components/admin/GeneralExpenseFormDialog.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
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
import { formatMoney } from '@/lib/format';

type ExpenseCategoryOption = {
    value: string;
    label: string;
};

type GeneralExpenseItem = {
    id: number;
    expense_date: string;
    category: string;
    category_label: string;
    amount: number;
    description: string | null;
    receipt_path: string | null;
    receipt_url: string | null;
    created_by_name: string | null;
    created_at: string | null;
};

type Props = {
    expenseCategories: ExpenseCategoryOption[];
    generalExpenses: GeneralExpenseItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'General Expenses',
                href: '/admin/general-expenses',
            },
        ],
    },
});

const props = defineProps<Props>();

const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const selectedExpense = ref<GeneralExpenseItem | null>(null);

const editableExpense = computed(() => selectedExpense.value);

const total = computed(() =>
    props.generalExpenses.reduce(
        (sum, expense) => sum + Number(expense.amount),
        0,
    ),
);

const openEditDialog = (expense: GeneralExpenseItem) => {
    selectedExpense.value = expense;
    isEditDialogOpen.value = true;
};
</script>

<template>
    <Head title="General Expenses" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="General expenses"
            description="Platform expenses such as printing, IT, utilities and transport. Paid from the platform fund."
        >
            <template #actions>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    Add expense
                </Button>
            </template>
        </PageHeader>

        <StatCard
            class="sm:max-w-xs"
            label="Total"
            :value="formatMoney(total)"
            :hint="`${generalExpenses.length} records`"
        />

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12 pl-4">SL</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Category</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead>Description</TableHead>
                        <TableHead>Added by</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(expense, index) in generalExpenses"
                        :key="expense.id"
                    >
                        <TableCell
                            class="pl-4 text-muted-foreground tabular-nums"
                            >{{ index + 1 }}</TableCell
                        >
                        <TableCell class="font-medium">
                            {{ expense.expense_date }}
                        </TableCell>
                        <TableCell>{{ expense.category_label }}</TableCell>
                        <TableCell class="text-right font-medium tabular-nums">
                            {{ formatMoney(expense.amount) }}
                        </TableCell>
                        <TableCell
                            class="max-w-sm whitespace-normal text-muted-foreground"
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
                        <TableCell class="pr-4 text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openEditDialog(expense)"
                            >
                                <SquarePen class="size-4" />
                                Edit
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableEmpty
                        v-if="generalExpenses.length === 0"
                        :colspan="7"
                    >
                        No expenses recorded yet.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

        <GeneralExpenseFormDialog
            v-model:isOpen="isCreateDialogOpen"
            mode="create"
            :expense-categories="props.expenseCategories"
        />

        <GeneralExpenseFormDialog
            v-model:isOpen="isEditDialogOpen"
            mode="edit"
            :expense-categories="props.expenseCategories"
            :general-expense="editableExpense"
        />
    </div>
</template>
