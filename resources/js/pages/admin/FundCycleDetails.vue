<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { SquarePen } from 'lucide-vue-next';
import { ref } from 'vue';
import FundCycleFormDialog from '@/components/admin/FundCycleFormDialog.vue';
import FundCycleLedgerSection from '@/components/admin/FundCycleLedgerSection.vue';
import type {
    CycleLedger,
    CycleTransaction,
} from '@/components/admin/FundCycleLedgerSection.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';

type FundCycleDetails = {
    is_settled: boolean;
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
    ledger: CycleLedger;
    transactions: CycleTransaction[];
    transactionCategories: { value: string; label: string }[];
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
</script>

<template>
    <Head :title="`${props.fundCycle.name} - Fund Cycle Details`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="props.fundCycle.name"
            :description="props.fundCycle.notes ?? undefined"
        >
            <template #actions>
                <Button
                    v-if="!props.fundCycle.is_settled"
                    variant="outline"
                    @click="isEditDialogOpen = true"
                >
                    <SquarePen class="size-4" />
                    Edit
                </Button>
                <Button variant="outline" as-child>
                    <Link
                        :href="`/admin/fund-cycles/${props.fundCycle.id}/events`"
                    >
                        Events
                    </Link>
                </Button>
                <Button as-child>
                    <Link
                        :href="`/admin/fund-cycles/${props.fundCycle.id}/allocations`"
                    >
                        Manage allocations
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div
            class="-mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
        >
            <StatusBadge
                :status="props.fundCycle.status"
                :label="props.fundCycle.status_label"
            />
            <span>{{ formatMoney(props.fundCycle.unit_amount) }} per unit</span>
            <span>Start {{ props.fundCycle.start_date || '—' }}</span>
            <span>Lock {{ props.fundCycle.lock_date || '—' }}</span>
            <span>Maturity {{ props.fundCycle.maturity_date || '—' }}</span>
            <span>Settlement {{ props.fundCycle.settlement_date || '—' }}</span>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Participants"
                :value="`${props.fundCycle.total_members} members`"
                :hint="`${props.fundCycle.total_users} users · ${props.fundCycle.total_units} units · ${props.fundCycle.total_slots} months`"
            />
            <StatCard
                label="Expected"
                :value="formatMoney(props.fundCycle.expected_amount)"
                :hint="`${props.fundCycle.expected_allocations} allocations`"
            />
            <StatCard
                label="Allocated"
                :value="formatMoney(props.fundCycle.allocated_amount)"
                :hint="`${props.fundCycle.allocations_count} allocations`"
                value-class="text-emerald-600 dark:text-emerald-400"
            />
            <StatCard
                label="Remaining"
                :value="formatMoney(props.fundCycle.remaining_amount)"
                :hint="`${props.fundCycle.remaining_allocations} allocations`"
                :value-class="
                    props.fundCycle.remaining_allocations > 0
                        ? 'text-amber-600 dark:text-amber-400'
                        : ''
                "
            />
        </div>

        <p class="-mt-3 text-xs text-muted-foreground">
            Created by {{ props.fundCycle.created_by || '—' }} ·
            {{ props.fundCycle.created_at || '—' }}
        </p>

        <FundCycleLedgerSection
            :cycle-id="props.fundCycle.id"
            :ledger="props.ledger"
            :transactions="props.transactions"
            :transaction-categories="props.transactionCategories"
        />

        <FundCycleFormDialog
            v-model:isOpen="isEditDialogOpen"
            mode="edit"
            :statuses="props.statuses"
            :fund-cycle="props.fundCycle"
        />
    </div>
</template>
