<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

const activeMemberId = ref<number | null>(
    props.memberTabs[0]?.member.id ?? null,
);
const cycleFilter = ref('');
const statusFilter = ref<'all' | 'allocated' | 'unallocated'>('all');
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

const activeMemberTab = computed<MemberTab | null>(() => {
    if (activeMemberId.value === null) {
        return null;
    }

    return (
        props.memberTabs.find(
            (tab) => tab.member.id === activeMemberId.value,
        ) ?? null
    );
});

watch(activeMemberId, () => {
    cycleFilter.value = '';
    statusFilter.value = 'all';
    slotFilter.value = '';
});

const filteredRows = computed<AllocationRow[]>(() => {
    if (!activeMemberTab.value) {
        return [];
    }

    return activeMemberTab.value.rows.filter((row) => {
        if (cycleFilter.value !== '' && row.cycle_name !== cycleFilter.value) {
            return false;
        }

        if (statusFilter.value !== 'all' && row.status !== statusFilter.value) {
            return false;
        }

        if (slotFilter.value.trim() !== '') {
            const slotText = (row.slot_key ?? '').toLowerCase();

            if (!slotText.includes(slotFilter.value.trim().toLowerCase())) {
                return false;
            }
        }

        return true;
    });
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

const selectMemberTab = (memberId: number) => {
    activeMemberId.value = memberId;
};

const openAllocateDialog = (row: AllocationRow) => {
    if (
        !activeMemberTab.value ||
        !row.can_allocate ||
        row.status !== 'unallocated'
    ) {
        return;
    }

    selectedAllocation.value = row;
    selectedMember.value = activeMemberTab.value.member;
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
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-for="tab in memberTabs"
                        :key="tab.member.id"
                        size="sm"
                        :variant="
                            activeMemberId === tab.member.id
                                ? 'default'
                                : 'outline'
                        "
                        @click="selectMemberTab(tab.member.id)"
                    >
                        {{ tab.member.full_name }}
                    </Button>
                </div>

                <div v-if="activeMemberTab" class="space-y-4">
                    <div
                        class="grid gap-2 border-b border-sidebar-border/70 pb-3 text-sm md:grid-cols-2 xl:grid-cols-4"
                    >
                        <p class="text-muted-foreground">
                            Member:
                            <span class="font-medium text-foreground">
                                {{ activeMemberTab.member.full_name }}
                            </span>
                        </p>
                        <p class="text-muted-foreground">
                            Units:
                            <span class="font-medium text-foreground">
                                {{ activeMemberTab.member.units }}
                            </span>
                        </p>
                        <p class="text-muted-foreground">
                            Activation:
                            <span class="font-medium text-foreground">
                                {{
                                    activeMemberTab.member.activated_at ||
                                    'Not active yet'
                                }}
                            </span>
                        </p>
                        <p class="text-muted-foreground">
                            Available Balance:
                            <span class="font-medium text-foreground">
                                {{ money(summary.available_to_allocate) }}
                            </span>
                        </p>
                    </div>

                    <div class="mt-4 grid gap-3 md:grid-cols-3">
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
                                    v-for="cycleName in activeMemberTab.filters
                                        .cycles"
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
                                >Status</label
                            >
                            <select
                                v-model="statusFilter"
                                class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                            >
                                <option value="all">All</option>
                                <option value="allocated">Allocated</option>
                                <option value="unallocated">Unallocated</option>
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

                    <div class="mt-4 space-y-3 md:hidden">
                        <article
                            v-for="row in filteredRows"
                            :key="`card-${row.row_key}`"
                            class="rounded-2xl border border-sidebar-border/70 bg-background p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
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
                                <Badge :variant="rowStatusVariant(row.status)">
                                    {{ rowStatusLabel(row.status) }}
                                </Badge>
                            </div>

                            <div class="mt-3 space-y-1 text-sm">
                                <p class="text-muted-foreground">
                                    Slot:
                                    <span class="font-medium text-foreground">
                                        {{ row.slot_key || 'No slot' }}
                                    </span>
                                </p>
                                <p class="text-muted-foreground">
                                    Amount:
                                    <span class="font-medium text-foreground">
                                        {{ money(row.amount) }}
                                    </span>
                                </p>
                                <p
                                    v-if="row.notes"
                                    class="text-muted-foreground"
                                >
                                    Note:
                                    <span class="font-medium text-foreground">
                                        {{ row.notes }}
                                    </span>
                                </p>
                            </div>

                            <div class="mt-3">
                                <p
                                    v-if="row.status === 'allocated'"
                                    class="text-sm text-muted-foreground"
                                >
                                    Allocated at:
                                    {{ row.allocated_at || 'Not recorded' }}
                                </p>
                                <Button
                                    v-else
                                    size="sm"
                                    class="w-full"
                                    :disabled="!row.can_allocate"
                                    @click="openAllocateDialog(row)"
                                >
                                    Allocate
                                </Button>
                            </div>
                        </article>

                        <div
                            v-if="filteredRows.length === 0"
                            class="rounded-2xl border border-dashed border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            No rows found for the selected filters.
                        </div>
                    </div>

                    <div class="mt-4 hidden overflow-x-auto md:block">
                        <table
                            class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                        >
                            <thead class="bg-muted/40 text-left">
                                <tr>
                                    <th class="px-4 py-3 font-medium">
                                        Fund Cycle
                                    </th>
                                    <th class="px-4 py-3 font-medium">Slot</th>
                                    <th class="px-4 py-3 font-medium">
                                        Amount
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        Allocated At
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-sidebar-border/70">
                                <tr
                                    v-for="row in filteredRows"
                                    :key="row.row_key"
                                    class="align-top"
                                >
                                    <td class="px-4 py-4">
                                        <p class="font-medium text-foreground">
                                            {{
                                                row.cycle_name ||
                                                'Unknown cycle'
                                            }}
                                        </p>
                                        <Badge
                                            class="mt-2"
                                            :variant="
                                                cycleStatusVariant(
                                                    row.cycle_status,
                                                )
                                            "
                                        >
                                            {{
                                                cycleStatusLabel(
                                                    row.cycle_status,
                                                )
                                            }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="font-medium text-foreground">
                                            {{ row.slot_key || 'No slot' }}
                                        </p>
                                        <p
                                            v-if="row.notes"
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ row.notes }}
                                        </p>
                                    </td>
                                    <td
                                        class="px-4 py-4 font-medium text-foreground"
                                    >
                                        {{ money(row.amount) }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <Badge
                                            :variant="
                                                rowStatusVariant(row.status)
                                            "
                                        >
                                            {{ rowStatusLabel(row.status) }}
                                        </Badge>
                                    </td>
                                    <td class="px-4 py-4 text-muted-foreground">
                                        <span v-if="row.status === 'allocated'">
                                            {{
                                                row.allocated_at ||
                                                'Not recorded'
                                            }}
                                        </span>
                                        <Button
                                            v-else
                                            size="sm"
                                            :disabled="!row.can_allocate"
                                            @click="openAllocateDialog(row)"
                                        >
                                            Allocate
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="filteredRows.length === 0">
                                    <td
                                        colspan="5"
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
