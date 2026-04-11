<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    Layers3,
    Lock,
    User,
    WalletCards,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type MemberItem = {
    id: number;
    full_name: string;
    status: string;
    units: number;
    approved_at: string | null;
    activated_at: string | null;
    remaining_pool: number;
};

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
    allocation_amount: number;
    total_allocated_amount: number;
    allocations_count: number;
    allocated_slots: string[];
    allocated_slot_amounts: Record<string, number>;
    is_locked: boolean;
    can_allocate: boolean;
};

type Props = {
    member: MemberItem;
    fundCycles: FundCycleItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Membership',
                href: '/my-membership',
            },
            {
                title: 'Available Fund Cycles',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();

const isAllocationDialogOpen = ref(false);
const selectedFundCycle = ref<FundCycleItem | null>(null);
const selectedSlot = ref('');
const isAllocationConfirmed = ref(false);

const form = useForm<{
    slot_key: string;
}>({
    slot_key: '',
});

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const hasSufficientBalance = (fundCycle: FundCycleItem): boolean =>
    props.member.remaining_pool >= fundCycle.allocation_amount;

const canOpenAllocationDialog = (
    fundCycle: FundCycleItem,
    slot: string,
): boolean => !fundCycle.is_locked && !fundCycle.allocated_slots.includes(slot);

const slotButtonVariant = (fundCycle: FundCycleItem, slot: string) => {
    if (fundCycle.allocated_slots.includes(slot)) {
        return 'outline';
    }

    return fundCycle.is_locked ? 'secondary' : 'outline';
};

const slotButtonLabel = (fundCycle: FundCycleItem, slot: string): string => {
    if (fundCycle.allocated_slots.includes(slot)) {
        return 'Allocated';
    }

    if (fundCycle.is_locked) {
        return 'Locked';
    }

    return 'Allocate';
};

const slotAmountLabel = (fundCycle: FundCycleItem, slot: string): string => {
    const paidAmount = fundCycle.allocated_slot_amounts[slot];

    if (paidAmount !== undefined) {
        return `Paid ${money(paidAmount)}`;
    }

    return `Pay ${money(fundCycle.allocation_amount)}`;
};

const slotButtonClass = (fundCycle: FundCycleItem, slot: string): string => {
    if (fundCycle.allocated_slots.includes(slot)) {
        return 'border-emerald-500/60 text-emerald-700 hover:border-emerald-500 hover:bg-background';
    }

    if (fundCycle.is_locked) {
        return 'opacity-70';
    }

    return '';
};

const slotIcon = (fundCycle: FundCycleItem, slot: string) => {
    if (fundCycle.allocated_slots.includes(slot)) {
        return CheckCircle2;
    }

    if (fundCycle.is_locked) {
        return Lock;
    }

    return WalletCards;
};

const selectedCycleName = computed(() => selectedFundCycle.value?.name ?? '');
const selectedAllocationAmount = computed(
    () => selectedFundCycle.value?.allocation_amount ?? 0,
);
const remainingAfterAllocation = computed(() =>
    Math.max(0, props.member.remaining_pool - selectedAllocationAmount.value),
);
const canConfirmAllocation = computed(
    () =>
        !!selectedFundCycle.value &&
        hasSufficientBalance(selectedFundCycle.value) &&
        isAllocationConfirmed.value,
);

const openAllocationDialog = (fundCycle: FundCycleItem, slot: string) => {
    if (!canOpenAllocationDialog(fundCycle, slot)) {
        return;
    }

    selectedFundCycle.value = fundCycle;
    selectedSlot.value = slot;
    form.defaults({
        slot_key: slot,
    });
    form.reset();
    form.clearErrors();
    isAllocationConfirmed.value = false;
    isAllocationDialogOpen.value = true;
};

const closeAllocationDialog = () => {
    isAllocationDialogOpen.value = false;
    selectedFundCycle.value = null;
    selectedSlot.value = '';
    isAllocationConfirmed.value = false;
    form.reset();
    form.clearErrors();
};

const submitAllocation = () => {
    if (!selectedFundCycle.value) {
        return;
    }

    form.transform((data) => ({
        slot_key: data.slot_key,
    })).post(
        `/my-membership/${props.member.id}/fund-cycles/${selectedFundCycle.value.id}/allocations`,
        {
            preserveScroll: true,
            onSuccess: () => closeAllocationDialog(),
        },
    );
};
</script>

<template>
    <Head title="Available Fund Cycles" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-3xl border border-sidebar-border/70 bg-background p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-2xl">
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Eligible Member
                    </p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight">
                        {{ member.full_name }}
                    </h1>
                    <p
                        class="mt-2 max-w-xl text-sm leading-6 text-muted-foreground"
                    >
                        Open fund cycles available for member allocation are
                        listed here.
                    </p>
                </div>

                <Button as-child variant="outline" class="shrink-0">
                    <Link href="/my-membership">
                        <ArrowLeft class="size-4" />
                        Back to Membership
                    </Link>
                </Button>
            </div>
        </section>

        <section v-if="fundCycles.length > 0" class="grid gap-4">
            <article
                v-for="fundCycle in fundCycles"
                :key="fundCycle.id"
                class="rounded-[26px] border border-sidebar-border/70 bg-background p-5 shadow-sm"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Cycle #{{ fundCycle.id }}
                        </p>
                        <h2 class="mt-2 text-xl font-semibold tracking-tight">
                            {{ fundCycle.name }}
                        </h2>
                    </div>

                    <Badge variant="outline">
                        {{ fundCycle.status_label }}
                    </Badge>
                </div>

                <div class="mt-5 grid gap-3 text-sm md:grid-cols-2">
                    <div class="rounded-2xl bg-background/75 px-3 py-3">
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <CalendarDays class="size-4" />
                            Timeline
                        </div>
                        <div class="mt-2 space-y-1 text-foreground">
                            <p>
                                Start: {{ fundCycle.start_date || 'Not set' }}
                            </p>
                            <p>Lock: {{ fundCycle.lock_date || 'Not set' }}</p>
                            <p>
                                Maturity:
                                {{ fundCycle.maturity_date || 'Not set' }}
                            </p>
                            <p>
                                Settlement:
                                {{ fundCycle.settlement_date || 'Not set' }}
                            </p>
                        </div>
                    </div>
                    <div class="rounded-2xl bg-background/75 px-3 py-3">
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <Layers3 class="size-4" />
                            Slots
                        </div>
                        <p class="mt-2 font-medium text-foreground">
                            {{ fundCycle.allocations_count }} /
                            {{ fundCycle.slots.length }} allocated
                        </p>
                        <p class="mt-2 text-sm text-muted-foreground">
                            Unit amount: {{ money(fundCycle.unit_amount) }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Total allocated:
                            {{ money(fundCycle.total_allocated_amount) }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Your slot amount:
                            {{ money(fundCycle.allocation_amount) }}
                        </p>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2">
                    <Button
                        v-for="slot in fundCycle.slots"
                        :key="slot"
                        :variant="slotButtonVariant(fundCycle, slot)"
                        size="sm"
                        class="h-auto min-h-16 min-w-32 cursor-pointer flex-col items-start rounded-2xl px-3 py-3 text-left"
                        :class="slotButtonClass(fundCycle, slot)"
                        @click="openAllocationDialog(fundCycle, slot)"
                    >
                        <span
                            class="flex w-full items-center gap-2 text-sm font-medium"
                        >
                            <component
                                :is="slotIcon(fundCycle, slot)"
                                class="size-4 shrink-0"
                            />
                            <span>{{ slot }}</span>
                        </span>
                        <span class="mt-1 w-full text-xs font-medium">
                            {{ slotAmountLabel(fundCycle, slot) }}
                        </span>
                        <span class="w-full text-xs text-muted-foreground">
                            {{ slotButtonLabel(fundCycle, slot) }}
                        </span>
                    </Button>
                </div>
            </article>
        </section>

        <section
            v-else
            class="rounded-[28px] border border-dashed border-sidebar-border/80 bg-background p-10 text-center shadow-sm"
        >
            <div class="mx-auto max-w-md">
                <p
                    class="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase"
                >
                    No Open Cycles
                </p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                    No available fund cycles found
                </h2>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Open fund cycles will appear here once they are available
                    for member allocation.
                </p>
            </div>
        </section>

        <Dialog
            :open="isAllocationDialogOpen"
            @update:open="isAllocationDialogOpen = $event"
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Allocate Slot</DialogTitle>
                    <DialogDescription>
                        Confirm this slot allocation for
                        {{ props.member.full_name }}.
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitAllocation">
                    <div class="rounded-2xl bg-muted/30 px-4 py-4 text-sm">
                        <div class="font-medium text-foreground">
                            {{ selectedCycleName }}
                        </div>
                        <div class="mt-2 text-muted-foreground">
                            Member: {{ props.member.full_name }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Slot: {{ selectedSlot || '-' }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Available to allocate:
                            {{ money(props.member.remaining_pool) }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Cycle unit amount:
                            {{
                                selectedFundCycle
                                    ? money(selectedFundCycle.unit_amount)
                                    : '-'
                            }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Member units: {{ props.member.units }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Allocation amount:
                            {{ money(selectedAllocationAmount) }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Remaining after confirm:
                            {{ money(remainingAfterAllocation) }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-dashed border-border/80 bg-background/70 px-4 py-4"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Member
                        </p>
                        <div
                            class="mt-2 flex items-center gap-2 text-lg font-semibold text-foreground"
                        >
                            <User class="size-4" />
                            {{ props.member.full_name }}
                        </div>
                    </div>

                    <div
                        class="flex items-start gap-3 rounded-2xl border border-dashed border-border/80 bg-background/70 px-4 py-4 text-sm"
                    >
                        <Checkbox
                            id="member-allocation-confirmation"
                            v-model="isAllocationConfirmed"
                            class="mt-0.5"
                        />
                        <label
                            for="member-allocation-confirmation"
                            class="leading-6 text-muted-foreground"
                        >
                            I confirm that
                            {{ money(selectedAllocationAmount) }} will be
                            allocated from my available deposit balance to this
                            fund cycle slot.
                        </label>
                    </div>

                    <InputError :message="form.errors.slot_key" />

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="secondary"
                            @click="closeAllocationDialog"
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            :disabled="form.processing || !canConfirmAllocation"
                        >
                            Confirm Allocation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
