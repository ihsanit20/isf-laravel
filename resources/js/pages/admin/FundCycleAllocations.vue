<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FundCycleAllocationDialog from '@/components/admin/FundCycleAllocationDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

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

    return Array.from(groups.entries()).map(([slotKey, allocations]) => ({
        slotKey,
        allocations,
    }));
});

const missingSlotGroups = computed(() => {
    const groups = new Map<string, MissingAllocation[]>();

    for (const missing of filteredMissingAllocations.value) {
        const slotKey = missing.slot_key || 'No slot';
        const items = groups.get(slotKey) ?? [];

        items.push(missing);
        groups.set(slotKey, items);
    }

    return Array.from(groups.entries()).map(([slotKey, allocations]) => ({
        slotKey,
        allocations,
    }));
});

const clearFilters = () => {
    selectedUser.value = '';
    selectedSlot.value = '';
    showMissingOnly.value = false;
};
</script>

<template>
    <Head :title="`${props.fundCycle.name} - Allocations`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-3xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ props.fundCycle.name }} Allocations
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Review allocated and missing slots by user, then post
                        new allocations.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        variant="default"
                        @click="isAllocateDialogOpen = true"
                    >
                        Allocate
                    </Button>
                    <Button variant="outline" as-child>
                        <Link
                            :href="`/admin/fund-cycles/${props.fundCycle.id}`"
                        >
                            Back to Details
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="mt-4 grid gap-4 text-sm md:grid-cols-3">
                <div>
                    <div class="text-xs text-muted-foreground">Status</div>
                    <div class="mt-1">
                        <Badge variant="outline">{{
                            props.fundCycle.status_label
                        }}</Badge>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Allocated</div>
                    <div class="mt-1 font-semibold text-foreground">
                        {{ props.fundCycle.allocations_count }} entries
                    </div>
                    <div class="mt-0.5 text-xs text-primary">
                        {{ money(props.fundCycle.allocated_amount) }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Remaining</div>
                    <div class="mt-1 font-semibold text-foreground">
                        {{ props.fundCycle.remaining_allocations }} entries
                    </div>
                    <div class="mt-0.5 text-xs text-primary">
                        {{ money(props.fundCycle.remaining_amount) }}
                    </div>
                </div>
            </div>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
        >
            <div class="flex flex-wrap items-center gap-3">
                <select
                    v-model="selectedUser"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
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
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
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

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div class="border-b border-sidebar-border/70 px-4 py-3">
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
                <table
                    class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                >
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Slot</th>
                            <th class="px-4 py-3 font-medium">User / Member</th>
                            <th class="px-4 py-3 font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Allocated At</th>
                            <th class="px-4 py-3 font-medium">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
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
                                    {{ money(allocation.amount) }}
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
                <table
                    class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                >
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Slot</th>
                            <th class="px-4 py-3 font-medium">User</th>
                            <th class="px-4 py-3 font-medium">Members</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
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
