<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import FundCycleFormDialog from '@/components/admin/FundCycleFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type FundCycleItem = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    unit_amount: number;
    start_date: string | null;
    lock_date: string | null;
    maturity_date: string | null;
    settlement_date: string | null;
    slots: string[];
    notes: string | null;
    has_allocations: boolean;
    created_by: string | null;
    created_at: string | null;
    allocated_amount: number;
    allocations_count: number;
};

type Props = {
    fundCycles: FundCycleItem[];
    statuses: string[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Fund Cycles',
                href: '/admin/fund-cycles',
            },
        ],
    },
});

const props = defineProps<Props>();
const isCreateDialogOpen = ref(false);

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;
</script>

<template>
    <Head title="Fund Cycles" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Fund Cycles
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Define cycle timeline and status before allocation,
                        investment, return, and settlement slices are added.
                    </p>
                </div>

                <div class="flex flex-col gap-3 md:items-end">
                    <Button class="shrink-0" @click="isCreateDialogOpen = true">
                        <Plus class="size-4" />
                        Add Fund Cycle
                    </Button>
                </div>
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
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Unit Price</th>
                            <th class="px-4 py-3 font-medium">Timeline</th>
                            <th class="px-4 py-3 font-medium">Slots</th>
                            <th class="px-4 py-3 font-medium">Allocations</th>
                            <th class="px-4 py-3 font-medium">Created By</th>
                            <th class="px-4 py-3 font-medium">Created At</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="fundCycle in fundCycles" :key="fundCycle.id">
                            <td class="px-4 py-3 font-medium">
                                <div>{{ fundCycle.name }}</div>
                                <div
                                    v-if="fundCycle.notes"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ fundCycle.notes }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge variant="outline">{{
                                    fundCycle.status_label
                                }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ money(fundCycle.unit_amount) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>
                                    Start: {{ fundCycle.start_date || '-' }}
                                </div>
                                <div>
                                    Lock: {{ fundCycle.lock_date || '-' }}
                                </div>
                                <div>
                                    Maturity:
                                    {{ fundCycle.maturity_date || '-' }}
                                </div>
                                <div>
                                    Settlement:
                                    {{ fundCycle.settlement_date || '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div
                                    v-if="fundCycle.slots.length > 0"
                                    class="space-y-1 text-xs"
                                >
                                    <div
                                        v-for="slot in fundCycle.slots"
                                        :key="slot"
                                    >
                                        {{ slot }}
                                    </div>
                                </div>
                                <div v-else class="text-xs">
                                    No slots configured
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div class="font-medium text-foreground">
                                    {{ money(fundCycle.allocated_amount) }}
                                </div>
                                <div
                                    v-if="fundCycle.allocations_count > 0"
                                    class="mt-1 text-xs"
                                >
                                    {{
                                        fundCycle.allocations_count.toLocaleString()
                                    }}
                                    entries
                                </div>
                                <div v-else class="mt-1 text-xs">
                                    No allocations yet
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ fundCycle.created_by || '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ fundCycle.created_at || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="`/admin/fund-cycles/${fundCycle.id}`"
                                    >
                                        View details
                                    </Link>
                                </Button>
                            </td>
                        </tr>
                        <tr v-if="fundCycles.length === 0">
                            <td
                                colspan="9"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No fund cycles found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <FundCycleFormDialog
            v-model:isOpen="isCreateDialogOpen"
            mode="create"
            :statuses="props.statuses"
        />
    </div>
</template>
