<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Landmark, Layers3, UsersRound, Wallet } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { amountToneClass, formatMoney, formatSignedMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

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

type TabMember = {
    id: number;
    full_name: string;
    status: string;
    units: number;
    activated_at: string | null;
    can_allocate: boolean;
};

type MemberTab = {
    member: TabMember;
    filters: {
        cycles: string[];
        slots: string[];
    };
    rows: AllocationRow[];
};

type CycleResult = {
    member_id: number;
    cycle_id: number;
    cycle_name: string;
    cycle_status: string;
    is_settled: boolean;
    settled_at: string | null;
    capital: number;
    share: number;
    returned: number | null;
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
    cycleResults: CycleResult[];
    selectedMemberId: number | null;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Investments', href: '/my-allocations' }],
    },
});

const props = defineProps<Props>();

const members = computed(() => props.memberTabs.map((tab) => tab.member));

const ALL_CYCLES = 'all';
const cycleFilter = ref(ALL_CYCLES);
const slotFilter = ref('');

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
    const match = slot?.trim().match(/^([A-Za-z]+)\s+(\d{4})$/);

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
    member: TabMember;
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
    props.memberTabs.map((tab) => ({
        member: tab.member,
        map: new Map(
            tab.rows.map((row) => [`${row.cycle_id}::${row.slot_key}`, row]),
        ),
    })),
);

const cycleOptions = computed(() => props.memberTabs[0]?.filters.cycles ?? []);

const pivotRows = computed<PivotRow[]>(() => {
    const baseRows = props.memberTabs[0]?.rows ?? [];
    const slotSearch = slotFilter.value.trim().toLowerCase();

    return baseRows
        .filter(
            (row) =>
                (cycleFilter.value === ALL_CYCLES ||
                    row.cycle_name === cycleFilter.value) &&
                (slotSearch === '' ||
                    (row.slot_key ?? '').toLowerCase().includes(slotSearch)),
        )
        .map((row) => {
            const key = `${row.cycle_id}::${row.slot_key}`;

            return {
                key,
                cycle_id: row.cycle_id,
                cycle_name: row.cycle_name,
                cycle_status: row.cycle_status,
                slot_key: row.slot_key,
                cells: memberRowMaps.value.map(({ member, map }) => ({
                    member,
                    row: map.get(key) ?? null,
                })),
            };
        })
        .sort(
            (a, b) =>
                slotSortValue(b.slot_key) - slotSortValue(a.slot_key) ||
                (a.cycle_name ?? '').localeCompare(b.cycle_name ?? ''),
        );
});

type MonthGroup = {
    slot_key: string | null;
    rows: PivotRow[];
};

const monthGroups = computed<MonthGroup[]>(() => {
    const groups: MonthGroup[] = [];

    pivotRows.value.forEach((row) => {
        const current = groups[groups.length - 1];

        if (current && current.slot_key === row.slot_key) {
            current.rows.push(row);
        } else {
            groups.push({ slot_key: row.slot_key, rows: [row] });
        }
    });

    return groups;
});

type ResultRow = {
    cycle_id: number;
    cycle_name: string;
    cycle_status: string;
    is_settled: boolean;
    byMember: Map<number, CycleResult>;
};

const resultRows = computed<ResultRow[]>(() => {
    const rows = new Map<number, ResultRow>();

    props.cycleResults.forEach((result) => {
        if (!rows.has(result.cycle_id)) {
            rows.set(result.cycle_id, {
                cycle_id: result.cycle_id,
                cycle_name: result.cycle_name,
                cycle_status: result.cycle_status,
                is_settled: result.is_settled,
                byMember: new Map(),
            });
        }

        rows.get(result.cycle_id)!.byMember.set(result.member_id, result);
    });

    return [...rows.values()];
});

const highlightClass = (memberId: number): string =>
    memberId === props.selectedMemberId ? 'bg-primary/5' : '';

const selectedRow = ref<AllocationRow | null>(null);
const selectedMember = ref<TabMember | null>(null);
const isDialogOpen = ref(false);

const form = useForm<{ slot_key: string; return_to: string }>({
    slot_key: '',
    return_to: 'allocations',
});

const balanceAfter = computed(
    () =>
        props.summary.available_to_allocate - (selectedRow.value?.amount ?? 0),
);

const openDialog = (member: TabMember, row: AllocationRow) => {
    if (!row.can_allocate || row.status !== 'unallocated') {
        return;
    }

    selectedMember.value = member;
    selectedRow.value = row;
    form.slot_key = row.slot_key ?? '';
    form.clearErrors();
    isDialogOpen.value = true;
};

const submit = () => {
    if (!selectedMember.value || !selectedRow.value) {
        return;
    }

    form.post(
        `/my-membership/${selectedMember.value.id}/fund-cycles/${selectedRow.value.cycle_id}/allocations`,
        {
            preserveScroll: true,
            onSuccess: () => {
                isDialogOpen.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Investments" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Investments"
            description="Every month of every cycle for all your members side by side. Allocate an open month straight from the table."
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Available to invest"
                :value="formatMoney(props.summary.available_to_allocate)"
                :icon="Wallet"
            />
            <StatCard
                label="Total invested"
                :value="formatMoney(props.summary.total_allocated_amount)"
                :hint="`${props.summary.total_allocations} allocations`"
                :icon="Layers3"
            />
            <StatCard
                label="Cycles joined"
                :value="props.summary.cycle_count"
                :icon="Landmark"
            />
            <StatCard
                label="Members"
                :value="props.summary.member_count"
                :icon="UsersRound"
            />
        </div>

        <EmptyState
            v-if="props.memberTabs.length === 0"
            :icon="UsersRound"
            title="No members yet"
            description="Investments are made per member. Add a member first."
        >
            <Button as-child>
                <Link href="/my-membership/create">Add member</Link>
            </Button>
        </EmptyState>

        <template v-else>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="member in members"
                    :key="member.id"
                    :class="
                        cn(
                            'flex items-center justify-between gap-3 rounded-xl border px-3 py-2',
                            highlightClass(member.id),
                        )
                    "
                >
                    <div class="min-w-0">
                        <p class="truncate font-medium">
                            {{ member.full_name }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ member.units }} unit{{
                                member.units > 1 ? 's' : ''
                            }}
                        </p>
                    </div>
                    <StatusBadge v-if="member.can_allocate" status="active" />
                    <Link
                        v-else
                        href="/my-membership"
                        class="text-xs text-amber-700 underline underline-offset-4 dark:text-amber-300"
                    >
                        Not active yet
                    </Link>
                </div>
            </div>

            <Card class="gap-4">
                <CardHeader>
                    <CardTitle>Allocations</CardTitle>
                    <CardDescription>
                        Each month costs the member’s units × the cycle’s unit
                        amount.
                    </CardDescription>
                </CardHeader>
                <CardContent class="grid gap-4">
                    <div class="grid gap-3 md:grid-cols-2">
                        <Select v-model="cycleFilter">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="All cycles" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="ALL_CYCLES">
                                    All cycles
                                </SelectItem>
                                <SelectItem
                                    v-for="cycleName in cycleOptions"
                                    :key="cycleName"
                                    :value="cycleName"
                                >
                                    {{ cycleName }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            v-model="slotFilter"
                            placeholder="Search month, e.g. May 2026"
                        />
                    </div>

                    <div class="grid gap-5 md:hidden">
                        <section
                            v-for="group in monthGroups"
                            :key="`m-${group.slot_key}`"
                            class="grid gap-3"
                        >
                            <h3
                                class="text-xs font-semibold tracking-widest text-muted-foreground uppercase"
                            >
                                {{ group.slot_key || 'No month' }}
                            </h3>
                            <div
                                v-for="row in group.rows"
                                :key="`card-${row.key}`"
                                class="rounded-xl border p-4"
                            >
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <p class="font-medium">
                                        {{ row.cycle_name }}
                                    </p>
                                    <StatusBadge :status="row.cycle_status" />
                                </div>
                                <div
                                    v-for="cell in row.cells"
                                    :key="cell.member.id"
                                    class="mt-3 flex items-center justify-between gap-3 border-t pt-3 text-sm"
                                >
                                    <div>
                                        <p class="font-medium">
                                            {{ cell.member.full_name }}
                                        </p>
                                        <p
                                            v-if="cell.row"
                                            class="text-muted-foreground tabular-nums"
                                        >
                                            {{ formatMoney(cell.row.amount) }}
                                        </p>
                                    </div>
                                    <span
                                        v-if="!cell.row"
                                        class="text-xs text-muted-foreground"
                                    >
                                        —
                                    </span>
                                    <div
                                        v-else-if="
                                            cell.row.status === 'allocated'
                                        "
                                        class="text-right"
                                    >
                                        <StatusBadge status="allocated" />
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ cell.row.allocated_at }}
                                        </p>
                                    </div>
                                    <Button
                                        v-else
                                        size="sm"
                                        :disabled="!cell.row.can_allocate"
                                        @click="
                                            openDialog(cell.member, cell.row)
                                        "
                                    >
                                        Allocate
                                    </Button>
                                </div>
                            </div>
                        </section>
                        <p
                            v-if="pivotRows.length === 0"
                            class="rounded-xl border border-dashed px-4 py-8 text-center text-sm text-muted-foreground"
                        >
                            No rows match the filters.
                        </p>
                    </div>

                    <div
                        class="hidden overflow-x-auto rounded-xl border md:block"
                    >
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="border-b">
                                    <th
                                        class="px-4 py-3 text-left font-medium text-muted-foreground"
                                    >
                                        Month
                                    </th>
                                    <th
                                        class="w-12 border-l px-4 py-3 text-left font-medium text-muted-foreground"
                                    >
                                        SL
                                    </th>
                                    <th
                                        class="border-l px-4 py-3 text-left font-medium text-muted-foreground"
                                    >
                                        Fund cycle
                                    </th>
                                    <th
                                        v-for="member in members"
                                        :key="member.id"
                                        :class="
                                            cn(
                                                'border-l px-4 py-3 text-center font-medium',
                                                highlightClass(member.id),
                                            )
                                        "
                                    >
                                        {{ member.full_name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template
                                    v-for="group in monthGroups"
                                    :key="`group-${group.slot_key}`"
                                >
                                    <tr
                                        v-for="(row, rowIndex) in group.rows"
                                        :key="row.key"
                                        class="border-b last:border-0"
                                    >
                                        <td
                                            v-if="rowIndex === 0"
                                            :rowspan="group.rows.length"
                                            class="px-4 py-3 align-middle font-medium whitespace-nowrap"
                                        >
                                            {{ group.slot_key || 'No month' }}
                                        </td>
                                        <td
                                            class="border-l px-4 py-3 align-middle text-muted-foreground tabular-nums"
                                        >
                                            {{ rowIndex + 1 }}
                                        </td>
                                        <td
                                            class="border-l px-4 py-3 align-middle"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span class="font-medium">
                                                    {{ row.cycle_name }}
                                                </span>
                                                <StatusBadge
                                                    :status="row.cycle_status"
                                                />
                                            </div>
                                        </td>
                                        <td
                                            v-for="cell in row.cells"
                                            :key="cell.member.id"
                                            :class="
                                                cn(
                                                    'border-l px-4 py-3 text-center align-middle',
                                                    highlightClass(
                                                        cell.member.id,
                                                    ),
                                                )
                                            "
                                        >
                                            <span
                                                v-if="!cell.row"
                                                class="text-xs text-muted-foreground"
                                            >
                                                —
                                            </span>
                                            <div
                                                v-else-if="
                                                    cell.row.status ===
                                                    'allocated'
                                                "
                                                class="grid justify-items-center gap-1"
                                            >
                                                <span
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        class="font-medium tabular-nums"
                                                    >
                                                        {{
                                                            formatMoney(
                                                                cell.row.amount,
                                                            )
                                                        }}
                                                    </span>
                                                    <StatusBadge
                                                        status="allocated"
                                                    />
                                                </span>
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    {{ cell.row.allocated_at }}
                                                </span>
                                            </div>
                                            <Button
                                                v-else
                                                size="sm"
                                                :disabled="
                                                    !cell.row.can_allocate
                                                "
                                                @click="
                                                    openDialog(
                                                        cell.member,
                                                        cell.row,
                                                    )
                                                "
                                            >
                                                Allocate
                                                <span
                                                    class="text-primary-foreground/70 tabular-nums"
                                                >
                                                    {{
                                                        formatMoney(
                                                            cell.row.amount,
                                                        )
                                                    }}
                                                </span>
                                            </Button>
                                        </td>
                                    </tr>
                                </template>
                                <tr v-if="pivotRows.length === 0">
                                    <td
                                        :colspan="3 + members.length"
                                        class="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No rows match the filters.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="resultRows.length > 0" class="gap-4">
                <CardHeader>
                    <CardTitle>Cycle results</CardTitle>
                    <CardDescription>
                        What each member invested in each cycle and what came
                        back. Running cycles show the profit or loss so far.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="overflow-x-auto rounded-xl border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr class="border-b">
                                    <th
                                        class="w-12 px-4 py-3 text-left font-medium text-muted-foreground"
                                    >
                                        SL
                                    </th>
                                    <th
                                        class="border-l px-4 py-3 text-left font-medium text-muted-foreground"
                                    >
                                        Fund cycle
                                    </th>
                                    <th
                                        v-for="member in members"
                                        :key="member.id"
                                        :class="
                                            cn(
                                                'border-l px-4 py-3 text-center font-medium',
                                                highlightClass(member.id),
                                            )
                                        "
                                    >
                                        {{ member.full_name }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(row, index) in resultRows"
                                    :key="row.cycle_id"
                                    class="border-b last:border-0"
                                >
                                    <td
                                        class="px-4 py-3 align-middle text-muted-foreground tabular-nums"
                                    >
                                        {{ index + 1 }}
                                    </td>
                                    <td class="border-l px-4 py-3 align-middle">
                                        <Link
                                            :href="`/fund-cycles/${row.cycle_id}`"
                                            class="font-medium hover:underline"
                                        >
                                            {{ row.cycle_name }}
                                        </Link>
                                        <div class="mt-1">
                                            <StatusBadge
                                                :status="row.cycle_status"
                                            />
                                        </div>
                                    </td>
                                    <td
                                        v-for="member in members"
                                        :key="member.id"
                                        :class="
                                            cn(
                                                'border-l px-4 py-3 align-middle',
                                                highlightClass(member.id),
                                            )
                                        "
                                    >
                                        <dl
                                            v-if="row.byMember.get(member.id)"
                                            class="mx-auto grid max-w-56 gap-1 text-xs"
                                        >
                                            <div
                                                class="flex justify-between gap-3"
                                            >
                                                <dt
                                                    class="text-muted-foreground"
                                                >
                                                    Invested
                                                </dt>
                                                <dd class="tabular-nums">
                                                    {{
                                                        formatMoney(
                                                            row.byMember.get(
                                                                member.id,
                                                            )!.capital,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                class="flex justify-between gap-3"
                                            >
                                                <dt
                                                    class="text-muted-foreground"
                                                >
                                                    {{
                                                        row.is_settled
                                                            ? 'Profit / loss'
                                                            : 'So far'
                                                    }}
                                                </dt>
                                                <dd
                                                    :class="
                                                        cn(
                                                            'tabular-nums',
                                                            amountToneClass(
                                                                row.byMember.get(
                                                                    member.id,
                                                                )!.share,
                                                            ),
                                                        )
                                                    "
                                                >
                                                    {{
                                                        formatSignedMoney(
                                                            row.byMember.get(
                                                                member.id,
                                                            )!.share,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                            <div
                                                v-if="row.is_settled"
                                                class="flex justify-between gap-3 border-t pt-1 font-medium"
                                            >
                                                <dt>Returned</dt>
                                                <dd class="tabular-nums">
                                                    {{
                                                        formatMoney(
                                                            row.byMember.get(
                                                                member.id,
                                                            )!.returned,
                                                        )
                                                    }}
                                                </dd>
                                            </div>
                                        </dl>
                                        <p
                                            v-else
                                            class="text-center text-xs text-muted-foreground"
                                        >
                                            —
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>
        </template>

        <Dialog v-model:open="isDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle
                        >Allocate to {{ selectedRow?.cycle_name }}</DialogTitle
                    >
                    <DialogDescription>
                        {{ selectedMember?.full_name }} ·
                        {{ selectedRow?.slot_key }}
                    </DialogDescription>
                </DialogHeader>

                <dl class="grid gap-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Available balance</dt>
                        <dd class="tabular-nums">
                            {{
                                formatMoney(props.summary.available_to_allocate)
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Allocation</dt>
                        <dd class="tabular-nums">
                            − {{ formatMoney(selectedRow?.amount) }}
                        </dd>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-medium">
                        <dt>Balance after</dt>
                        <dd
                            class="tabular-nums"
                            :class="
                                balanceAfter < 0
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : ''
                            "
                        >
                            {{ formatMoney(balanceAfter) }}
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="balanceAfter < 0"
                    class="text-sm text-rose-600 dark:text-rose-400"
                >
                    Not enough balance.
                    <Link href="/my-deposits/create" class="underline">
                        Submit a deposit
                    </Link>
                    first.
                </p>
                <InputError :message="form.errors.slot_key" />

                <DialogFooter>
                    <Button variant="outline" @click="isDialogOpen = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="form.processing || balanceAfter < 0"
                        @click="submit"
                    >
                        Confirm allocation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
