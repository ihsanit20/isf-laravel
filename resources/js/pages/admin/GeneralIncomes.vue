<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, SquarePen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import GeneralIncomeFormDialog from '@/components/admin/GeneralIncomeFormDialog.vue';
import { Button } from '@/components/ui/button';

type IncomeCategoryOption = {
    value: string;
    label: string;
};

type GeneralIncomeItem = {
    id: number;
    income_date: string;
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
    incomeCategories: IncomeCategoryOption[];
    generalIncomes: GeneralIncomeItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'General Incomes',
                href: '/admin/general-incomes',
            },
        ],
    },
});

const props = defineProps<Props>();

const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const selectedIncome = ref<GeneralIncomeItem | null>(null);

const editableIncome = computed(() => selectedIncome.value);

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const openEditDialog = (income: GeneralIncomeItem) => {
    selectedIncome.value = income;
    isEditDialogOpen.value = true;
};
</script>

<template>
    <Head title="General Incomes" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        General Incomes
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Keep independent records of general incomes like
                        donations, membership fees, bank interest, and
                        sponsorships.
                    </p>
                </div>

                <Button class="shrink-0" @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    Add Income
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
                            <th class="px-4 py-3 font-medium">Attachment</th>
                            <th class="px-4 py-3 font-medium">Added By</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="income in generalIncomes"
                            :key="income.id"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ income.income_date }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ income.category_label }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ money(income.amount) }}
                            </td>
                            <td
                                class="max-w-sm px-4 py-3 text-muted-foreground"
                            >
                                {{ income.description || '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <a
                                    v-if="income.receipt_url"
                                    :href="income.receipt_url"
                                    target="_blank"
                                    class="text-primary underline underline-offset-4"
                                >
                                    View Attachment
                                </a>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ income.created_by_name || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openEditDialog(income)"
                                >
                                    <SquarePen class="size-4" />
                                    Edit
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="generalIncomes.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No general incomes found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <GeneralIncomeFormDialog
            v-model:isOpen="isCreateDialogOpen"
            mode="create"
            :income-categories="props.incomeCategories"
        />

        <GeneralIncomeFormDialog
            v-model:isOpen="isEditDialogOpen"
            mode="edit"
            :income-categories="props.incomeCategories"
            :general-income="editableIncome"
        />
    </div>
</template>
