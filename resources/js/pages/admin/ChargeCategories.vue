<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, SquarePen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ChargeCategoryFormDialog from '@/components/admin/ChargeCategoryFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const openEditDialog = (category: ChargeCategoryItem) => {
    selectedCategory.value = category;
    isEditDialogOpen.value = true;
};
</script>

<template>
    <Head title="Charge Categories" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Charge Categories
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Manage reusable member charge types and their default
                        amounts without coupling system logic to editable
                        titles.
                    </p>
                </div>

                <Button class="shrink-0" @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    Add Category
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
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Title</th>
                            <th class="px-4 py-3 font-medium">
                                Default Amount
                            </th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Created At</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="category in chargeCategories"
                            :key="category.id"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ category.code }}
                                <Badge
                                    v-if="category.is_system"
                                    variant="outline"
                                    class="ml-2"
                                    >System</Badge
                                >
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ category.title }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ money(category.default_amount) }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        category.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        category.is_active
                                            ? 'Active'
                                            : 'Inactive'
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ category.created_at || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    @click="openEditDialog(category)"
                                >
                                    <SquarePen class="size-4" />
                                    Edit
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="chargeCategories.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No charge categories found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

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
