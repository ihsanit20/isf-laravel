<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, SquarePen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ChargeCategoryFormDialog from '@/components/admin/ChargeCategoryFormDialog.vue';
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
import { formatMoney } from '@/lib/format';

type ChargeCategoryItem = {
    id: number;
    code: string;
    title: string;
    default_amount: number;
    is_active: boolean;
    is_system: boolean;
    created_at: string | null;
};

type Props = {
    chargeCategories: ChargeCategoryItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Charge Categories',
                href: '/admin/charge-categories',
            },
        ],
    },
});

defineProps<Props>();

const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const selectedCategory = ref<ChargeCategoryItem | null>(null);

const editableCategory = computed(() => selectedCategory.value);

const openEditDialog = (category: ChargeCategoryItem) => {
    selectedCategory.value = category;
    isEditDialogOpen.value = true;
};
</script>

<template>
    <Head title="Charge Categories" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Charge categories"
            description="Member charge types and their default amounts. System categories keep their code; the title can change."
        >
            <template #actions>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    Add category
                </Button>
            </template>
        </PageHeader>

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12 pl-4">SL</TableHead>
                        <TableHead>Title</TableHead>
                        <TableHead>Code</TableHead>
                        <TableHead class="text-right">Default amount</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Created</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(category, index) in chargeCategories"
                        :key="category.id"
                    >
                        <TableCell
                            class="pl-4 text-muted-foreground tabular-nums"
                            >{{ index + 1 }}</TableCell
                        >
                        <TableCell class="font-medium">
                            {{ category.title }}
                        </TableCell>
                        <TableCell>
                            <code class="text-xs">{{ category.code }}</code>
                            <span
                                v-if="category.is_system"
                                class="ml-2 rounded-md bg-muted px-1.5 py-0.5 text-xs"
                            >
                                System
                            </span>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ formatMoney(category.default_amount) }}
                        </TableCell>
                        <TableCell>
                            <StatusBadge
                                :status="
                                    category.is_active ? 'active' : 'inactive'
                                "
                            />
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ category.created_at || '—' }}
                        </TableCell>
                        <TableCell class="pr-4 text-right">
                            <Button
                                variant="outline"
                                size="sm"
                                @click="openEditDialog(category)"
                            >
                                <SquarePen class="size-4" />
                                Edit
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableEmpty
                        v-if="chargeCategories.length === 0"
                        :colspan="7"
                    >
                        No charge categories yet.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

        <ChargeCategoryFormDialog
            v-model:isOpen="isCreateDialogOpen"
            mode="create"
        />

        <ChargeCategoryFormDialog
            v-model:isOpen="isEditDialogOpen"
            mode="edit"
            :charge-category="editableCategory"
        />
    </div>
</template>
