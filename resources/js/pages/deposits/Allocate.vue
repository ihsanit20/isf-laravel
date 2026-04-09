<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type DepositItem = {
    total_deposit_amount: number;
    total_verified_amount: number;
    total_allocated_amount: number;
    total_allocatable_amount: number;
    total_deposit_count: number;
    can_allocate: boolean;
};

type MemberItem = {
    id: number;
    full_name: string;
    units: number;
    monthly_due_amount: number;
};

type Props = {
    summary: DepositItem;
    members: MemberItem[];
};

type AllocationRow = {
    member_id: string;
    allocation_month: string;
    units: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Deposits',
                href: '/my-deposits',
            },
            {
                title: 'Allocate Units',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();

const newRow = (): AllocationRow => ({
    member_id: '',
    allocation_month: new Date().toISOString().slice(0, 7),
    units: 1,
});

const form = useForm<{ rows: AllocationRow[] }>({
    rows: [newRow()],
});

const membersById = computed(() =>
    Object.fromEntries(
        props.members.map((member) => [String(member.id), member]),
    ),
);

const totalAllocatedDraftAmount = computed(() =>
    form.rows.reduce((sum, row) => sum + Math.max(row.units || 0, 0) * 1000, 0),
);

const remainingAfterDraft = computed(
    () =>
        props.summary.total_allocatable_amount -
        totalAllocatedDraftAmount.value,
);

const addRow = () => {
    form.rows.push(newRow());
};

const removeRow = (index: number) => {
    if (form.rows.length === 1) {
        form.rows[0] = newRow();

        return;
    }

    form.rows.splice(index, 1);
};

const rowError = (
    index: number,
    field: keyof AllocationRow,
): string | undefined =>
    form.errors[`rows.${index}.${field}` as keyof typeof form.errors] as
        | string
        | undefined;

const submit = () => {
    form.transform((data) => ({
        rows: data.rows.map((row) => ({
            ...row,
            member_id: Number(row.member_id),
        })),
    })).post('/my-deposits/allocate', {
        preserveScroll: true,
    });
};

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;
</script>

<template>
    <Head title="Allocate Units" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-emerald-50 p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-semibold tracking-tight">
                        Allocate Units
                    </h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        Allocate units from your total verified deposit pool.
                        Individual deposit submissions are not allocated one by
                        one.
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link href="/my-deposits">
                        <ArrowLeft class="size-4" />
                        Back to My Deposits
                    </Link>
                </Button>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
            <div
                class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
            >
                <form class="space-y-5" @submit.prevent="submit">
                    <div
                        v-for="(row, index) in form.rows"
                        :key="index"
                        class="rounded-3xl border border-border/70 bg-muted/20 p-4"
                    >
                        <div class="grid gap-4 md:grid-cols-3">
                            <div class="grid gap-2">
                                <Label :for="`member-${index}`">Member</Label>
                                <Select v-model="row.member_id">
                                    <SelectTrigger
                                        :id="`member-${index}`"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Select member"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="member in props.members"
                                            :key="member.id"
                                            :value="String(member.id)"
                                        >
                                            {{ member.full_name }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="rowError(index, 'member_id')"
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`month-${index}`">Month</Label>
                                <Input
                                    :id="`month-${index}`"
                                    v-model="row.allocation_month"
                                    type="month"
                                />
                                <InputError
                                    :message="
                                        rowError(index, 'allocation_month')
                                    "
                                />
                            </div>

                            <div class="grid gap-2">
                                <Label :for="`units-${index}`">Units</Label>
                                <Input
                                    :id="`units-${index}`"
                                    v-model.number="row.units"
                                    type="number"
                                    min="1"
                                />
                                <InputError
                                    :message="rowError(index, 'units')"
                                />
                            </div>
                        </div>

                        <div
                            v-if="membersById[row.member_id]"
                            class="mt-3 rounded-2xl bg-background px-4 py-3 text-sm text-muted-foreground"
                        >
                            {{ membersById[row.member_id].full_name }} has
                            {{ membersById[row.member_id].units }} unit monthly
                            dues worth
                            {{
                                money(
                                    membersById[row.member_id]
                                        .monthly_due_amount,
                                )
                            }}.
                        </div>

                        <div class="mt-4 flex justify-end">
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                @click="removeRow(index)"
                            >
                                <Trash2 class="size-4" />
                                Remove Row
                            </Button>
                        </div>
                    </div>

                    <InputError :message="form.errors.rows" />

                    <div class="flex flex-wrap items-center gap-3">
                        <Button type="button" variant="outline" @click="addRow">
                            <Plus class="size-4" />
                            Add Another Row
                        </Button>
                        <Button
                            type="submit"
                            :disabled="
                                form.processing ||
                                remainingAfterDraft < 0 ||
                                props.members.length === 0
                            "
                        >
                            Confirm Allocation
                        </Button>
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                <section
                    class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        Allocation Pool Summary
                    </h2>
                    <div class="mt-4 grid gap-3 text-sm">
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Total deposited
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{ money(props.summary.total_deposit_amount) }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Verified deposits
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{ money(props.summary.total_verified_amount) }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Already allocated
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{
                                    money(props.summary.total_allocated_amount)
                                }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Available balance
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{
                                    money(
                                        props.summary.total_allocatable_amount,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Draft allocation
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{ money(totalAllocatedDraftAmount) }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                After confirm
                            </p>
                            <p
                                class="mt-2 font-semibold"
                                :class="
                                    remainingAfterDraft < 0
                                        ? 'text-rose-600'
                                        : 'text-foreground'
                                "
                            >
                                {{ money(remainingAfterDraft) }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        Approved Members
                    </h2>
                    <div v-if="props.members.length > 0" class="mt-4 space-y-3">
                        <div
                            v-for="member in props.members"
                            :key="member.id"
                            class="rounded-2xl border border-border/70 px-4 py-4"
                        >
                            <p class="font-medium text-foreground">
                                {{ member.full_name }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ member.units }} unit monthly due •
                                {{ money(member.monthly_due_amount) }}
                            </p>
                        </div>
                    </div>
                    <p
                        v-else
                        class="mt-4 text-sm leading-6 text-muted-foreground"
                    >
                        No approved members are available for allocation yet.
                    </p>
                </section>
            </div>
        </section>
    </div>
</template>
