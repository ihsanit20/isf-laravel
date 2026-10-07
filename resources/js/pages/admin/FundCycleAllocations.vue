<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FundCycleAllocationDialog from '@/components/admin/FundCycleAllocationDialog.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { formatMoney } from '@/lib/format';

type AllocationItem = {
    id: number;
    member_id: number;
    member_name: string | null;
    user_id: number | null;
    user_name: string | null;
    slot_key: string | null;
    amount: number;
    allocated_at: string | null;
    notes: string | null;
};

type MissingAllocation = {
    user_id: number;
    user_name: string;
    user_phone: string;
    member_names: string;
    slot_key: string;
};

type UserWithMembers = {
    id: number;
    name: string;
    email: string;
    member_names: string;
};

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
    allocations: AllocationItem[];
};

type EligibleMember = {
    id: number;
    full_name: string;
    units: number;
};

type Props = {
    fundCycle: FundCycleDetails;
    users: UserWithMembers[];
    missingAllocations: MissingAllocation[];
    eligibleMembers: EligibleMember[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Fund Cycles',
                href: '/admin/fund-cycles',
            },
            {
                title: 'Allocations',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();

const selectedUser = ref<string>('');
const selectedSlot = ref<string>('');
const showMissingOnly = ref(false);
const isAllocateDialogOpen = ref(false);

const MONTHS = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

const slotSortValue = (slotKey: string): number => {
    const [month, year] = slotKey.trim().split(' ');
    const monthIndex = MONTHS.indexOf(month);

    if (monthIndex === -1 || !year) {
        return -Infinity;
    }

    return Number(year) * 12 + monthIndex;
};

const sortBySlotDesc = <T extends { slotKey: string }>(groups: T[]): T[] =>
    groups.sort((a, b) => slotSortValue(b.slotKey) - slotSortValue(a.slotKey));

const filteredAllocations = computed(() => {
    let filtered = props.fundCycle.allocations;

    if (selectedUser.value) {
        filtered = filtered.filter(
            (a) => a.user_id === parseInt(selectedUser.value),
        );
    }

    if (selectedSlot.value) {
        filtered = filtered.filter((a) => a.slot_key === selectedSlot.value);
    }

    return filtered;
});

const filteredMissingAllocations = computed(() => {
    let filtered = props.missingAllocations;

    if (selectedUser.value) {
        filtered = filtered.filter(
            (a) => a.user_id === parseInt(selectedUser.value),
        );
    }

    if (selectedSlot.value) {
        filtered = filtered.filter((a) => a.slot_key === selectedSlot.value);
    }

    return filtered;
});

const slotGroups = computed(() => {
    const allocationsToShow = showMissingOnly.value
        ? []
        : filteredAllocations.value;

    const groups = new Map<string, AllocationItem[]>();

    for (const allocation of allocationsToShow) {
        const slotKey = allocation.slot_key || 'No slot';
        const items = groups.get(slotKey) ?? [];

        items.push(allocation);
        groups.set(slotKey, items);
    }

    return sortBySlotDesc(
        Array.from(groups.entries()).map(([slotKey, allocations]) => ({
            slotKey,
            allocations,
        })),
    );
});

const missingSlotGroups = computed(() => {
    const groups = new Map<string, MissingAllocation[]>();

    for (const missing of filteredMissingAllocations.value) {
        const slotKey = missing.slot_key || 'No slot';
        const items = groups.get(slotKey) ?? [];

        items.push(missing);
        groups.set(slotKey, items);
    }

    return sortBySlotDesc(
        Array.from(groups.entries()).map(([slotKey, allocations]) => ({
            slotKey,
            allocations,
        })),
    );
});

const clearFilters = () => {
    selectedUser.value = '';
    selectedSlot.value = '';
    showMissingOnly.value = false;
};
</script>

<template>
    <Head :title="`${props.fundCycle.name} - Allocations`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`${props.fundCycle.name} · Allocations`"
            description="Allocated and missing months by user. Post an allocation on a member’s behalf from here."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="`/admin/fund-cycles/${props.fundCycle.id}`">
                        Cycle details
                    </Link>
                </Button>
                <Button
                    v-if="!props.fundCycle.is_settled"
                    @click="isAllocateDialogOpen = true"
                >
                    <Plus class="size-4" />
                    Allocate
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 sm:grid-cols-3">
            <div
                class="flex items-center rounded-xl border bg-card p-4 shadow-xs"
            >
                <StatusBadge
                    :status="props.fundCycle.status"
                    :label="props.fundCycle.status_label"
                />
            </div>
            <StatCard
                label="Allocated"
                :value="formatMoney(props.fundCycle.allocated_amount)"
                :hint="`${props.fundCycle.allocations_count} entries`"
                value-class="text-emerald-600 dark:text-emerald-400"
            />
            <StatCard
                label="Missing"
                :value="formatMoney(props.fundCycle.remaining_amount)"
                :hint="`${props.fundCycle.remaining_allocations} entries`"
                :value-class="
                    props.fundCycle.remaining_allocations > 0
                        ? 'text-amber-600 dark:text-amber-400'
                        : ''
                "
            />
        </div>

        <section class="rounded-xl border bg-card p-4 shadow-xs">
            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="selectedUser"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
                >
                    <option value="">All Users</option>
                    <option
                        v-for="user in props.users"
                        :key="user.id"
                        :value="user.id"
                    >
                        {{ user.name }} ({{ user.member_names }})
                    </option>
                </select>

                <select
                    v-model="selectedSlot"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
                >
                    <option value="">All Slots</option>
                    <option
                        v-for="slot in props.fundCycle.slots"
                        :key="slot"
                        :value="slot"
                    >
                        {{ slot }}
                    </option>
                </select>

                <label class="flex items-center gap-2 text-sm">
                    <input
                        v-model="showMissingOnly"
                        type="checkbox"
                        class="size-4 rounded border-input"
                    />
                    <span>Show Missing Only</span>
                </label>

                <Button
                    variant="outline"
                    size="sm"
                    class="h-9"
                    @click="clearFilters"
                >
                    Clear Filters
                </Button>

                <div class="ml-auto text-sm text-muted-foreground">
                    <span v-if="!showMissingOnly">
                        {{ filteredAllocations.length }} /
                        {{ props.fundCycle.allocations_count }} allocations
                    </span>
                    <span v-else>
                        {{ filteredMissingAllocations.length }} /
                        {{ props.missingAllocations.length }} missing
                    </span>
                </div>
            </div>
        </section>

        <section class="overflow-hidden rounded-xl border bg-card shadow-xs">
            <div class="border-b px-4 py-3">
                <h2 class="text-base font-medium">
                    {{
                        showMissingOnly
                            ? 'Missing Allocations'
                            : 'Allocation Details'
                    }}
                </h2>
            </div>

            <div
                v-if="!showMissingOnly && slotGroups.length > 0"
                class="overflow-x-auto"
            >
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Slot</th>
                            <th class="w-12 px-4 py-3 font-medium">SL</th>
                            <th class="px-4 py-3 font-medium">User / Member</th>
                            <th class="px-4 py-3 font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Allocated At</th>
                            <th class="px-4 py-3 font-medium">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <template
                            v-for="slotGroup in slotGroups"
                            :key="slotGroup.slotKey"
                        >
                            <tr
                                v-for="(
                                    allocation, index
                                ) in slotGroup.allocations"
                                :key="allocation.id"
                            >
                                <td
                                    v-if="index === 0"
                                    :rowspan="slotGroup.allocations.length"
                                    class="px-4 py-3 align-top text-muted-foreground"
                                >
                                    {{ slotGroup.slotKey }}
                                </td>
                                <td
                                    class="px-4 py-3 text-muted-foreground tabular-nums"
                                >
                                    {{ index + 1 }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <div>
                                        {{
                                            allocation.user_name ||
                                            'Unknown user'
                                        }}
                                    </div>
                                    <div class="text-xs">
                                        {{ allocation.member_name || '-' }}
                                    </div>
                                </td>
                                <td
                                    class="px-4 py-3 font-medium text-foreground"
                                >
                                    {{ formatMoney(allocation.amount) }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ allocation.allocated_at || '-' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ allocation.notes || '-' }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div
                v-else-if="showMissingOnly && missingSlotGroups.length > 0"
                class="overflow-x-auto"
            >
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Slot</th>
                            <th class="w-12 px-4 py-3 font-medium">SL</th>
                            <th class="px-4 py-3 font-medium">User</th>
                            <th class="px-4 py-3 font-medium">Members</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <template
                            v-for="slotGroup in missingSlotGroups"
                            :key="slotGroup.slotKey"
                        >
                            <tr
                                v-for="(
                                    missing, index
                                ) in slotGroup.allocations"
                                :key="`${missing.user_id}-${missing.slot_key}`"
                            >
                                <td
                                    v-if="index === 0"
                                    :rowspan="slotGroup.allocations.length"
                                    class="px-4 py-3 align-top text-muted-foreground"
                                >
                                    {{ slotGroup.slotKey }}
                                </td>
                                <td
                                    class="px-4 py-3 text-muted-foreground tabular-nums"
                                >
                                    {{ index + 1 }}
                                </td>
                                <td class="px-4 py-3 text-destructive">
                                    {{ missing.user_name }}
                                    <div class="text-xs">
                                        {{ missing.user_phone }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ missing.member_names }}
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div
                v-else
                class="px-4 py-8 text-center text-sm text-muted-foreground"
            >
                {{
                    showMissingOnly
                        ? 'No missing allocations found.'
                        : 'No allocations found for this fund cycle.'
                }}
            </div>
        </section>

        <FundCycleAllocationDialog
            v-model:isOpen="isAllocateDialogOpen"
            :fund-cycle="props.fundCycle"
            :eligible-members="props.eligibleMembers"
        />
    </div>
</template>
