<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, SquarePen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import GeneralExpenseFormDialog from '@/components/admin/GeneralExpenseFormDialog.vue';
import { Button } from '@/components/ui/button';

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

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const openEditDialog = (expense: GeneralExpenseItem) => {
    selectedExpense.value = expense;
    isEditDialogOpen.value = true;
};
</script>

<template>
    <Head title="General Expenses" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        General Expenses
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Keep independent records of routine admin expenses like
                        printing, IT, utilities, and transport.
                    </p>
                </div>

                <Button class="shrink-0" @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    Add Expense
                </Button>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                >
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Date</th>
                            <th class="px-4 py-3 font-medium">Category</th>
                            <th class="px-4 py-3 font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 font-medium">Receipt</th>
                            <th class="px-4 py-3 font-medium">Added By</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="expense in generalExpenses"
                            :key="expense.id"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ expense.expense_date }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ expense.category_label }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ money(expense.amount) }}
                            </td>
                            <td
                                class="max-w-sm px-4 py-3 text-muted-foreground"
                            >
                                {{ expense.description || '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <a
                                    v-if="expense.receipt_url"
                                    :href="expense.receipt_url"
                                    target="_blank"
                                    class="text-primary underline underline-offset-4"
                                >
                                    View Attachment
                                </a>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ expense.created_by_name || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openEditDialog(expense)"
                                >
                                    <SquarePen class="size-4" />
                                    Edit
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="generalExpenses.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No general expenses found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

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
