<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

type AllocationRow = {
    row_key: string;
    id: number | null;
    status: 'allocated' | 'unallocated';
    cycle_id: number;
    cycle_name: string | null;
    cycle_status: string | null;
    slot_key: string | null;
    amount: number;
    allocated_at: string | null;
    notes: string | null;
    can_allocate: boolean;
};

type MemberTab = {
    member: {
        id: number;
        full_name: string;
        status: string;
        units: number;
        activated_at: string | null;
        can_allocate: boolean;
    };
    filters: {
        cycles: string[];
        slots: string[];
    };
    rows: AllocationRow[];
};

type Props = {
    summary: {
        total_allocations: number;
        total_allocated_amount: number;
        member_count: number;
        cycle_count: number;
        available_to_allocate: number;
    };
    memberTabs: MemberTab[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Allocations',
                href: '/my-allocations',
            },
        ],
    },
});

const props = defineProps<Props>();

const cycleFilter = ref('');
const slotFilter = ref('');

const isAllocateDialogOpen = ref(false);
const selectedAllocation = ref<AllocationRow | null>(null);
const selectedMember = ref<MemberTab['member'] | null>(null);

const form = useForm<{
    slot_key: string;
}>({
    slot_key: '',
});

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const MONTH_NAMES = [
    'january',
    'february',
    'march',
    'april',
    'may',
    'june',
    'july',
    'august',
    'september',
    'october',
    'november',
    'december',
];

const slotSortValue = (slot: string | null): number => {
    if (!slot) {
        return -Infinity;
    }

    const match = slot.trim().match(/^([A-Za-z]+)\s+(\d{4})$/);

    if (!match) {
        return -Infinity;
    }

    const monthIndex = MONTH_NAMES.indexOf(match[1].toLowerCase());

    if (monthIndex === -1) {
        return -Infinity;
    }

    return Number(match[2]) * 12 + monthIndex;
};

type PivotCell = {
    member: MemberTab['member'];
    row: AllocationRow | null;
};

type PivotRow = {
    key: string;
    cycle_id: number;
    cycle_name: string | null;
    cycle_status: string | null;
    slot_key: string | null;
    cells: PivotCell[];
};

const memberRowMaps = computed(() =>
    props.memberTabs.map((tab) => {
        const map = new Map<string, AllocationRow>();

        tab.rows.forEach((row) => {
            map.set(`${row.cycle_id}::${row.slot_key}`, row);
        });

        return { member: tab.member, map };
    }),
);

const filterOptions = computed(() => props.memberTabs[0]?.filters ?? null);

const pivotRows = computed<PivotRow[]>(() => {
    const baseRows = props.memberTabs[0]?.rows ?? [];

    return baseRows
        .map((row) => {
            const rowKey = `${row.cycle_id}::${row.slot_key}`;

            return {
                key: rowKey,
                cycle_id: row.cycle_id,
                cycle_name: row.cycle_name,
                cycle_status: row.cycle_status,
                slot_key: row.slot_key,
                cells: memberRowMaps.value.map(({ member, map }) => ({
                    member,
                    row: map.get(rowKey) ?? null,
                })),
            };
        })
        .filter((row) => {
            if (
                cycleFilter.value !== '' &&
                row.cycle_name !== cycleFilter.value
            ) {
                return false;
            }

            if (slotFilter.value.trim() !== '') {
                const slotText = (row.slot_key ?? '').toLowerCase();

                if (
                    !slotText.includes(slotFilter.value.trim().toLowerCase())
                ) {
                    return false;
                }
            }

            return true;
        })
        .sort((a, b) => {
            const slotCompare =
                slotSortValue(b.slot_key) - slotSortValue(a.slot_key);

            if (slotCompare !== 0) {
                return slotCompare;
            }

            return (a.cycle_name ?? '').localeCompare(b.cycle_name ?? '');
        });
});

type MonthGroup = {
    slot_key: string | null;
    rows: PivotRow[];
};

const monthGroups = computed<MonthGroup[]>(() => {
    const groups: MonthGroup[] = [];

    pivotRows.value.forEach((row) => {
        const currentGroup = groups[groups.length - 1];

        if (!currentGroup || currentGroup.slot_key !== row.slot_key) {
            groups.push({ slot_key: row.slot_key, rows: [row] });

            return;
        }

        currentGroup.rows.push(row);
    });

    return groups;
});

const selectedAllocationAmount = computed(
    () => selectedAllocation.value?.amount ?? 0,
);

const remainingAfterAllocation = computed(() =>
    Math.max(
        0,
        props.summary.available_to_allocate - selectedAllocationAmount.value,
    ),
);

const cycleStatusVariant = (
    status: string | null,
): 'default' | 'secondary' | 'outline' => {
    if (status === 'open') {
        return 'default';
    }

    if (status === 'locked' || status === 'matured') {
        return 'secondary';
    }

    return 'outline';
};

const cycleStatusLabel = (status: string | null): string => {
    if (!status) {
        return 'Unknown';
    }

    return status
        .replace('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
};

const rowStatusVariant = (
    status: AllocationRow['status'],
): 'default' | 'secondary' =>
    status === 'allocated' ? 'default' : 'secondary';

const rowStatusLabel = (status: AllocationRow['status']): string =>
    status === 'allocated' ? 'Allocated' : 'Unallocated';

const openAllocateDialog = (
    member: MemberTab['member'],
    row: AllocationRow | null,
) => {
    if (!row || !row.can_allocate || row.status !== 'unallocated') {
        return;
    }

    selectedAllocation.value = row;
    selectedMember.value = member;
    form.defaults({
        slot_key: row.slot_key ?? '',
    });
    form.reset();
    form.clearErrors();
    isAllocateDialogOpen.value = true;
};

const closeAllocateDialog = () => {
    isAllocateDialogOpen.value = false;
    selectedAllocation.value = null;
    selectedMember.value = null;
    form.reset();
    form.clearErrors();
};

const submitAllocation = () => {
    if (!selectedMember.value || !selectedAllocation.value) {
        return;
    }

    form.transform((data) => ({
        slot_key: data.slot_key,
        return_to: 'allocations',
    })).post(
        `/my-membership/${selectedMember.value.id}/fund-cycles/${selectedAllocation.value.cycle_id}/allocations`,
        {
            preserveScroll: true,
            onSuccess: () => closeAllocateDialog(),
        },
    );
};
</script>

<template>
    <Head title="My Allocations" />

    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-background shadow-sm"
        >
            <div class="border-b border-sidebar-border/70 px-6 py-5">
                <p
                    class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                >
                    My Allocations
                </p>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-foreground"
                >
                    Allocation List by Member
                </h1>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    Review allocated and unallocated cycle slots for each member
                    in one place.
                </p>
            </div>

            <div v-if="memberTabs.length > 0" class="space-y-4 p-4">
                <div class="space-y-4">
                    <div
                        class="grid gap-3 border-b border-sidebar-border/70 pb-3 text-sm sm:grid-cols-2 lg:grid-cols-3"
                    >
                        <div
                            v-for="tab in memberTabs"
                            :key="tab.member.id"
                            class="rounded-xl border border-sidebar-border/70 px-3 py-2"
                        >
                            <p class="font-medium text-foreground">
                                {{ tab.member.full_name }}
                            </p>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Units: {{ tab.member.units }} · Activation:
                                {{
                                    tab.member.activated_at ||
                                    'Not active yet'
                                }}
                            </p>
                        </div>
                    </div>

                    <p class="text-sm text-muted-foreground">
                        Available Balance:
                        <span class="font-medium text-foreground">
                            {{ money(summary.available_to_allocate) }}
                        </span>
                    </p>

                    <div v-if="filterOptions" class="mt-4 grid gap-3 md:grid-cols-2">
                        <div>
                            <label
                                class="mb-1 block text-xs text-muted-foreground"
                                >Cycle</label
                            >
                            <select
                                v-model="cycleFilter"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="">All cycles</option>
                                <option
                                    v-for="cycleName in filterOptions.cycles"
                                    :key="cycleName"
                                    :value="cycleName"
                                >
                                    {{ cycleName }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-xs text-muted-foreground"
                                >Slot contains</label
                            >
                            <Input
                                v-model="slotFilter"
                                placeholder="Search by slot"
                            />
                        </div>
                    </div>

                    <div class="mt-4 space-y-5 md:hidden">
                        <section
                            v-for="group in monthGroups"
                            :key="`m-${group.slot_key}`"
                            class="space-y-3"
                        >
                            <h3
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                            >
                                {{ group.slot_key || 'No slot' }}
                            </h3>

                            <article
                                v-for="row in group.rows"
                                :key="`card-${row.key}`"
                                class="rounded-2xl border border-sidebar-border/70 bg-background p-4"
                            >
                                <div>
                                    <p class="font-medium text-foreground">
                                        {{ row.cycle_name || 'Unknown cycle' }}
                                    </p>
                                    <Badge
                                        class="mt-2"
                                        :variant="
                                            cycleStatusVariant(row.cycle_status)
                                        "
                                    >
                                        {{ cycleStatusLabel(row.cycle_status) }}
                                    </Badge>
                                </div>

                                <div
                                    v-for="cell in row.cells"
                                    :key="cell.member.id"
                                    class="mt-3 flex items-start justify-between gap-3 border-t border-sidebar-border/70 pt-3"
                                >
                                    <div class="text-sm">
                                        <p class="font-medium text-foreground">
                                            {{ cell.member.full_name }}
                                        </p>
                                        <p
                                            v-if="cell.row"
                                            class="text-muted-foreground"
                                        >
                                            {{ money(cell.row.amount) }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <template v-if="cell.row">
                                            <Badge
                                                :variant="
                                                    rowStatusVariant(
                                                        cell.row.status,
                                                    )
                                                "
                                            >
                                                {{
                                                    rowStatusLabel(
                                                        cell.row.status,
                                                    )
                                                }}
                                            </Badge>
                                            <p
                                                v-if="
                                                    cell.row.status ===
                                                    'allocated'
                                                "
                                                class="mt-1 text-xs text-muted-foreground"
                                            >
                                                {{
                                                    cell.row.allocated_at ||
                                                    'Not recorded'
                                                }}
                                            </p>
                                            <Button
                                                v-else
                                                size="sm"
                                                class="mt-2"
                                                :disabled="!cell.row.can_allocate"
                                                @click="
                                                    openAllocateDialog(
                                                        cell.member,
                                                        cell.row,
                                                    )
                                                "
                                            >
                                                Allocate
                                            </Button>
                                        </template>
                                        <span
                                            v-else
                                            class="text-xs text-muted-foreground"
                                        >
                                            N/A
                                        </span>
                                    </div>
                                </div>
                            </article>
                        </section>

                        <div
                            v-if="pivotRows.length === 0"
                            class="rounded-2xl border border-dashed border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            No rows found for the selected filters.
                        </div>
                    </div>

                    <div class="mt-4 hidden overflow-x-auto md:block">
                        <table
                            class="min-w-full divide-y divide-sidebar-border/70 text-sm border border-sidebar-border/70 rounded-2xl"
                        >
                            <thead class="bg-muted/40 text-left">
                                <tr>
                                    <th class="px-4 py-3 font-medium text-center">
                                        Month
                                    </th>
                                    <th class="px-4 py-3 font-medium text-center">
                                        Fund Cycle
                                    </th>
                                    <th
                                        v-for="tab in memberTabs"
                                        :key="tab.member.id"
                                        class="px-4 py-3 font-medium text-center"
                                    >
                                        {{ tab.member.full_name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <template
                                    v-for="group in monthGroups"
                                    :key="`group-${group.slot_key}`"
                                >
                                    <tr
                                        v-for="(row, rowIndex) in group.rows"
                                        :key="row.key"
                                        class="align-top"
                                    >
                                        <td
                                            v-if="rowIndex === 0"
                                            :rowspan="group.rows.length"
                                            class="px-4 py-4 font-medium text-foreground text-center align-middle"
                                        >
                                            <div class="flex justify-center items-center">
                                                {{ group.slot_key || 'No slot' }}
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 border-l border-sidebar-border/70 align-middle">
                                            <div class="flex gap-x-4 justify-center items-center font-medium text-foreground">
                                                {{
                                                    row.cycle_name ||
                                                    'Unknown cycle'
                                                }}
                                            </div>
                                        </td>
                                        <td
                                            v-for="cell in row.cells"
                                            :key="cell.member.id"
                                            class="px-4 py-4 border-l border-sidebar-border/70"
                                        >
                                            <div v-if="cell.row" class="flex flex-wrap justify-center items-center gap-x-4">
                                                <p class="font-medium text-foreground">
                                                    {{ money(cell.row.amount) }}
                                                </p>
                                                <Badge
                                                    class="mt-1"
                                                    :variant="
                                                        rowStatusVariant(
                                                            cell.row.status,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        rowStatusLabel(
                                                            cell.row.status,
                                                        )
                                                    }}
                                                </Badge>
                                                <p
                                                    v-if="
                                                        cell.row.status ===
                                                        'allocated'
                                                    "
                                                    class="mt-1 text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        cell.row.allocated_at ||
                                                        'Not recorded'
                                                    }}
                                                </p>
                                                <Button
                                                    v-else
                                                    size="sm"
                                                    class="mt-2"
                                                    :disabled="!cell.row.can_allocate"
                                                    @click="
                                                        openAllocateDialog(
                                                            cell.member,
                                                            cell.row,
                                                        )
                                                    "
                                                >
                                                    Allocate
                                                </Button>
                                            </div>
                                            <span
                                                v-else
                                                class="text-xs text-muted-foreground"
                                            >
                                                N/A
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="pivotRows.length === 0">
                                    <td
                                        :colspan="2 + memberTabs.length"
                                        class="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No rows found for the selected filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div v-else class="px-6 py-10 text-sm text-muted-foreground">
                No members found under your account yet.
            </div>
        </section>

        <Dialog
            :open="isAllocateDialogOpen"
            @update:open="isAllocateDialogOpen = $event"
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Allocate Slot</DialogTitle>
                    <DialogDescription>
                        Review allocation summary and confirm.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitAllocation">
                    <div class="rounded-2xl bg-muted/30 px-4 py-4 text-sm">
                        <div class="font-medium text-foreground">
                            {{ selectedMember?.full_name || '-' }}
                        </div>
                        <div class="mt-2 text-muted-foreground">
                            Fund cycle:
                            {{ selectedAllocation?.cycle_name || '-' }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Slot: {{ selectedAllocation?.slot_key || '-' }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Allocation amount:
                            {{ money(selectedAllocationAmount) }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Available to allocate:
                            {{ money(summary.available_to_allocate) }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Remaining after confirm:
                            {{ money(remainingAfterAllocation) }}
                        </div>
                    </div>

                    <InputError :message="form.errors.slot_key" />

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            @click="closeAllocateDialog"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            Confirm Allocation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
