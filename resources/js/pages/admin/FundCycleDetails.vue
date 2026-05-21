<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import FundCycleFormDialog from '@/components/admin/FundCycleFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type FundCycleDetails = {
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
    total_users: number;
    total_members: number;
    total_units: number;
    total_slots: number;
    expected_allocations: number;
    expected_amount: number;
    allocated_amount: number;
    allocations_count: number;
    remaining_allocations: number;
    remaining_amount: number;
};

type Props = {
    fundCycle: FundCycleDetails;
    statuses: string[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Fund Cycles',
                href: '/admin/fund-cycles',
            },
            {
                title: 'Details',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();

const isEditDialogOpen = ref(false);

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;
</script>

<template>
    <Head :title="`${props.fundCycle.name} - Fund Cycle Details`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-3xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ props.fundCycle.name }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Detailed allocation history for this fund cycle.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button variant="default" as-child>
                        <Link
                            :href="`/admin/fund-cycles/${props.fundCycle.id}/allocations`"
                        >
                            Manage Allocations
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link
                            :href="`/admin/fund-cycles/${props.fundCycle.id}/events`"
                        >
                            Events
                        </Link>
                    </Button>
                    <Button variant="outline" @click="isEditDialogOpen = true">
                        Edit
                    </Button>
                    <Button variant="outline" as-child>
                        <Link href="/admin/fund-cycles">
                            Back to Fund Cycles
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="mt-6 grid gap-4 text-sm md:grid-cols-3 lg:grid-cols-6">
                <div>
                    <div class="text-xs text-muted-foreground">Status</div>
                    <div class="mt-1">
                        <Badge variant="outline">{{
                            props.fundCycle.status_label
                        }}</Badge>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Unit Price</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ money(props.fundCycle.unit_amount) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Total Users</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.total_users }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">
                        Total Members
                    </div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.total_members }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Total Units</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.total_units }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Total Slots</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.total_slots }}
                    </div>
                </div>
            </div>

            <div
                class="mt-4 grid gap-4 border-t border-sidebar-border/70 pt-4 text-sm md:grid-cols-3"
            >
                <div>
                    <div class="text-xs text-muted-foreground">
                        Expected Allocations
                    </div>
                    <div class="mt-1 font-semibold text-foreground">
                        {{ props.fundCycle.expected_allocations }} entries
                    </div>
                    <div class="mt-0.5 text-xs text-primary">
                        {{ money(props.fundCycle.expected_amount) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Allocated</div>
                    <div
                        class="mt-1 font-semibold text-green-600 dark:text-green-500"
                    >
                        {{ props.fundCycle.allocations_count }} entries
                    </div>
                    <div
                        class="mt-0.5 text-xs text-green-600 dark:text-green-500"
                    >
                        {{ money(props.fundCycle.allocated_amount) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Remaining</div>
                    <div
                        class="mt-1 font-semibold text-orange-600 dark:text-orange-500"
                    >
                        {{ props.fundCycle.remaining_allocations }} entries
                    </div>
                    <div
                        class="mt-0.5 text-xs text-orange-600 dark:text-orange-500"
                    >
                        {{ money(props.fundCycle.remaining_amount) }}
                    </div>
                </div>
            </div>

            <div
                class="mt-4 grid gap-3 border-t border-sidebar-border/70 pt-4 text-sm text-muted-foreground md:grid-cols-2"
            >
                <div>Start: {{ props.fundCycle.start_date || '-' }}</div>
                <div>Lock: {{ props.fundCycle.lock_date || '-' }}</div>
                <div>Maturity: {{ props.fundCycle.maturity_date || '-' }}</div>
                <div>
                    Settlement: {{ props.fundCycle.settlement_date || '-' }}
                </div>
            </div>

            <div
                class="mt-4 grid gap-3 border-t border-sidebar-border/70 pt-4 text-sm text-muted-foreground md:grid-cols-2"
            >
                <div>Created By: {{ props.fundCycle.created_by || '-' }}</div>
                <div>Created At: {{ props.fundCycle.created_at || '-' }}</div>
            </div>

            <div
                v-if="props.fundCycle.notes"
                class="mt-4 border-t border-sidebar-border/70 pt-4 text-sm text-muted-foreground"
            >
                <div class="text-xs text-muted-foreground">Notes</div>
                <div class="mt-1">{{ props.fundCycle.notes }}</div>
            </div>
        </section>

        <FundCycleFormDialog
            v-model:isOpen="isEditDialogOpen"
            mode="edit"
            :statuses="props.statuses"
            :fund-cycle="props.fundCycle"
        />
    </div>
</template>
