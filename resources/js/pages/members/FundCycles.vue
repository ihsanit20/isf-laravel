<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Layers3, Lock } from 'lucide-vue-next';
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

type MemberItem = {
    id: number;
    full_name: string;
    status: string;
    units: number;
    approved_at: string | null;
    activated_at: string | null;
    allocation_amount: number;
    remaining_pool: number;
};

type FundCycleItem = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    start_date: string | null;
    lock_date: string | null;
    maturity_date: string | null;
    settlement_date: string | null;
    slots: string[];
    allocations_count: number;
    allocated_slots: string[];
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

const form = useForm<{
    slot_key: string;
    notes: string;
}>({
    slot_key: '',
    notes: '',
});

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const canAllocateSlot = (fundCycle: FundCycleItem, slot: string): boolean =>
    fundCycle.can_allocate && !fundCycle.allocated_slots.includes(slot);

const slotButtonVariant = (fundCycle: FundCycleItem, slot: string) => {
    if (fundCycle.allocated_slots.includes(slot)) {
        return 'default';
    }

    return fundCycle.can_allocate ? 'outline' : 'secondary';
};

const slotButtonLabel = (fundCycle: FundCycleItem, slot: string): string => {
    if (fundCycle.allocated_slots.includes(slot)) {
        return 'Allocated';
    }

    if (fundCycle.is_locked) {
        return 'Locked';
    }

    if (!fundCycle.can_allocate) {
        return 'Unavailable';
    }

    return 'Allocate';
};

const allocationStatusNote = (fundCycle: FundCycleItem): string => {
    if (fundCycle.is_locked) {
        return 'This cycle is locked. New slot allocations are disabled.';
    }

    if (props.member.remaining_pool < props.member.allocation_amount) {
        return 'Your verified deposit balance is not enough for another slot allocation.';
    }

    return 'Choose any available slot to allocate this member into the cycle.';
};

const selectedCycleName = computed(() => selectedFundCycle.value?.name ?? '');

const openAllocationDialog = (fundCycle: FundCycleItem, slot: string) => {
    if (!canAllocateSlot(fundCycle, slot)) {
        return;
    }

    selectedFundCycle.value = fundCycle;
    selectedSlot.value = slot;
    form.defaults({
        slot_key: slot,
        notes: '',
    });
    form.reset();
    form.clearErrors();
    isAllocationDialogOpen.value = true;
};

const closeAllocationDialog = () => {
    isAllocationDialogOpen.value = false;
    selectedFundCycle.value = null;
    selectedSlot.value = '';
    form.reset();
    form.clearErrors();
};

const submitAllocation = () => {
    if (!selectedFundCycle.value) {
        return;
    }

    form.transform((data) => ({
        slot_key: data.slot_key,
        notes: data.notes || null,
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

        <section v-if="fundCycles.length > 0" class="grid gap-4 xl:grid-cols-2">
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

                <div class="mt-5 grid gap-3 text-sm">
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
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ allocationStatusNote(fundCycle) }}
                        </p>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Button
                                v-for="slot in fundCycle.slots"
                                :key="slot"
                                :variant="slotButtonVariant(fundCycle, slot)"
                                size="sm"
                                class="h-auto min-h-16 min-w-32 flex-col items-start rounded-2xl px-3 py-3 text-left"
                                :disabled="!canAllocateSlot(fundCycle, slot)"
                                @click="openAllocationDialog(fundCycle, slot)"
                            >
                                <span class="w-full text-sm font-medium">{{
                                    slot
                                }}</span>
                                <span
                                    class="w-full text-xs text-muted-foreground"
                                >
                                    {{ slotButtonLabel(fundCycle, slot) }}
                                </span>
                            </Button>
                        </div>
                    </div>
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
                            Slot: {{ selectedSlot || '-' }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Member units: {{ props.member.units }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Allocation amount:
                            {{ money(props.member.allocation_amount) }}
                        </div>
                        <div class="mt-1 text-muted-foreground">
                            Available balance:
                            {{ money(props.member.remaining_pool) }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-dashed border-border/80 bg-background/70 px-4 py-4 text-sm text-muted-foreground"
                    >
                        <div class="flex items-center gap-2">
                            <Lock class="size-4" />
                            Allocation is blocked automatically once the cycle
                            reaches its lock date.
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <label
                            class="text-sm font-medium text-foreground"
                            for="member-allocation-notes"
                        >
                            Notes
                        </label>
                        <textarea
                            id="member-allocation-notes"
                            v-model="form.notes"
                            rows="3"
                            class="flex min-h-20 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="Optional allocation note"
                        />
                        <InputError :message="form.errors.notes" />
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
                            :disabled="
                                form.processing ||
                                !selectedFundCycle ||
                                props.member.remaining_pool <
                                    props.member.allocation_amount
                            "
                        >
                            Confirm Allocation
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
