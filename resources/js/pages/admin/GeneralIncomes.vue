<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText, Plus, SquarePen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import GeneralIncomeFormDialog from '@/components/admin/GeneralIncomeFormDialog.vue';
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

const total = computed(() =>
    props.generalIncomes.reduce(
        (sum, income) => sum + Number(income.amount),
        0,
    ),
);

const openEditDialog = (income: GeneralIncomeItem) => {
    selectedIncome.value = income;
    isEditDialogOpen.value = true;
};
</script>

<template>
    <Head title="General Incomes" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="General incomes"
            description="Platform income such as donations, bank interest and sponsorships. Added to the platform fund."
        >
            <template #actions>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    Add income
                </Button>
            </template>
        </PageHeader>

        <StatCard
            class="sm:max-w-xs"
            label="Total"
            :value="formatMoney(total)"
            :hint="`${generalIncomes.length} records`"
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
                        v-for="(income, index) in generalIncomes"
                        :key="income.id"
                    >
                        <TableCell
                            class="pl-4 text-muted-foreground tabular-nums"
                            >{{ index + 1 }}</TableCell
                        >
                        <TableCell class="font-medium">
                            {{ income.income_date }}
                        </TableCell>
                        <TableCell>{{ income.category_label }}</TableCell>
                        <TableCell class="text-right font-medium tabular-nums">
                            {{ formatMoney(income.amount) }}
                        </TableCell>
                        <TableCell
                            class="max-w-sm whitespace-normal text-muted-foreground"
                        >
                            {{ income.description || '—' }}
                            <a
                                v-if="income.receipt_url"
                                :href="income.receipt_url"
                                target="_blank"
                                rel="noopener"
                                class="mt-1 flex items-center gap-1 text-xs text-foreground underline underline-offset-4"
                            >
                                <FileText class="size-3" />
                                Attachment
                            </a>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ income.created_by_name || '—' }}
                        </TableCell>
                        <TableCell class="pr-4 text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openEditDialog(income)"
                            >
                                <SquarePen class="size-4" />
                                Edit
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="generalIncomes.length === 0" :colspan="7">
                        No incomes recorded yet.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

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
