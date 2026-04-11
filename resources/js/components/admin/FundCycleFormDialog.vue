<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Plus, X } from 'lucide-vue-next';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type EditableFundCycle = {
    id: number;
    name: string;
    status: string;
    start_date: string | null;
    lock_date: string | null;
    maturity_date: string | null;
    settlement_date: string | null;
    slots: string[];
    notes: string | null;
};

type Props = {
    mode: 'create' | 'edit';
    statuses: string[];
    fundCycle?: EditableFundCycle | null;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const isEditing = computed(() => props.mode === 'edit' && !!props.fundCycle);

const formatSlotLabel = (dateValue: string): string => {
    const [year, month] = dateValue.split('-').map(Number);

    if (!year || !month) {
        return '';
    }

    return new Intl.DateTimeFormat('en-US', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(year, month - 1, 1));
};

const buildAutoSlots = (startDate: string, lockDate: string): string[] => {
    if (!startDate) {
        return [];
    }

    const [startYear, startMonth] = startDate.split('-').map(Number);
    const [lockYear, lockMonth] = (lockDate || startDate)
        .split('-')
        .map(Number);

    if (!startYear || !startMonth || !lockYear || !lockMonth) {
        return [];
    }

    const slots: string[] = [];
    const cursor = new Date(startYear, startMonth - 1, 1);
    const end = new Date(lockYear, lockMonth - 1, 1);

    while (cursor <= end) {
        slots.push(
            new Intl.DateTimeFormat('en-US', {
                month: 'long',
                year: 'numeric',
            }).format(cursor),
        );

        cursor.setMonth(cursor.getMonth() + 1);
    }

    return slots;
};

const form = useForm<{
    name: string;
    status: string;
    start_date: string;
    lock_date: string;
    maturity_date: string;
    settlement_date: string;
    slots: string[];
    notes: string;
}>({
    name: '',
    status: props.statuses[0] ?? 'draft',
    start_date: '',
    lock_date: '',
    maturity_date: '',
    settlement_date: '',
    slots: [],
    notes: '',
});

const labelize = (value: string): string =>
    value.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const resetFormState = () => {
    const values =
        isEditing.value && props.fundCycle
            ? {
                  name: props.fundCycle.name,
                  status: props.fundCycle.status,
                  start_date: props.fundCycle.start_date ?? '',
                  lock_date: props.fundCycle.lock_date ?? '',
                  maturity_date: props.fundCycle.maturity_date ?? '',
                  settlement_date: props.fundCycle.settlement_date ?? '',
                  slots: [...props.fundCycle.slots],
                  notes: props.fundCycle.notes ?? '',
              }
            : {
                  name: '',
                  status: props.statuses[0] ?? 'draft',
                  start_date: '',
                  lock_date: '',
                  maturity_date: '',
                  settlement_date: '',
                  slots: [],
                  notes: '',
              };

    form.defaults(values);
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    isOpen.value = false;
    resetFormState();
};

const submit = () => {
    const slots = form.slots
        .map((slot) => slot.trim())
        .filter(
            (slot, index, allSlots) =>
                slot !== '' && allSlots.indexOf(slot) === index,
        );

    const payload = {
        ...form.data(),
        lock_date: form.lock_date || null,
        maturity_date: form.maturity_date || null,
        settlement_date: form.settlement_date || null,
        slots,
        notes: form.notes || null,
    };

    if (isEditing.value && props.fundCycle) {
        form.transform(() => payload).put(
            `/admin/fund-cycles/${props.fundCycle.id}`,
            {
                preserveScroll: true,
                onSuccess: () => closeDialog(),
            },
        );

        return;
    }

    form.transform(() => payload).post('/admin/fund-cycles', {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

const addSlot = () => {
    form.slots = [...form.slots, ''];
};

const removeSlot = (index: number) => {
    form.slots = form.slots.filter((_, slotIndex) => slotIndex !== index);
};

watch(
    () => [form.start_date, form.lock_date],
    ([startDate, lockDate], [previousStartDate, previousLockDate]) => {
        const previousAutoSlots = buildAutoSlots(
            previousStartDate ?? '',
            previousLockDate ?? '',
        );
        const nextAutoSlots = buildAutoSlots(startDate ?? '', lockDate ?? '');

        const currentSlots = [...form.slots];
        const shouldReplaceSlots =
            currentSlots.length === 0 ||
            JSON.stringify(currentSlots) === JSON.stringify(previousAutoSlots);

        if (shouldReplaceSlots) {
            form.slots = nextAutoSlots;

            return;
        }

        const manualSlots = currentSlots.filter(
            (slot) => !previousAutoSlots.includes(slot),
        );
        const retainedRemovedSlots = currentSlots.filter(
            (slot) =>
                previousAutoSlots.includes(slot) &&
                !nextAutoSlots.includes(slot),
        );

        form.slots = [...nextAutoSlots, ...manualSlots, ...retainedRemovedSlots]
            .map((slot) => slot.trim())
            .filter(
                (slot, index, allSlots) =>
                    slot !== '' && allSlots.indexOf(slot) === index,
            );
    },
);

watch(
    () => [
        isOpen.value,
        props.mode,
        props.fundCycle?.id,
        props.statuses.join('|'),
    ],
    ([open]) => {
        if (open) {
            resetFormState();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Fund Cycle' : 'Add Fund Cycle' }}
                </DialogTitle>
                <DialogDescription>
                    Capture the fund cycle timeline before allocations,
                    investment, and settlement work starts.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="fund-cycle-name">Name</Label>
                    <Input
                        id="fund-cycle-name"
                        v-model="form.name"
                        placeholder="April 2026 Cycle"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="fund-cycle-status">Status</Label>
                        <Select v-model="form.status">
                            <SelectTrigger
                                id="fund-cycle-status"
                                class="w-full"
                            >
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="status in statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ labelize(status) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fund-cycle-start-date">Start Date</Label>
                        <Input
                            id="fund-cycle-start-date"
                            v-model="form.start_date"
                            type="date"
                        />
                        <InputError :message="form.errors.start_date" />
                    </div>
                </div>

                <div class="grid gap-2 md:grid-cols-3">
                    <div class="grid gap-2">
                        <Label for="fund-cycle-lock-date">Lock Date</Label>
                        <Input
                            id="fund-cycle-lock-date"
                            v-model="form.lock_date"
                            type="date"
                        />
                        <InputError :message="form.errors.lock_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fund-cycle-maturity-date"
                            >Maturity Date</Label
                        >
                        <Input
                            id="fund-cycle-maturity-date"
                            v-model="form.maturity_date"
                            type="date"
                        />
                        <InputError :message="form.errors.maturity_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="fund-cycle-settlement-date"
                            >Settlement Date</Label
                        >
                        <Input
                            id="fund-cycle-settlement-date"
                            v-model="form.settlement_date"
                            type="date"
                        />
                        <InputError :message="form.errors.settlement_date" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="fund-cycle-slots">Slots</Label>
                    <div class="flex flex-wrap gap-2">
                        <div
                            v-for="(slot, index) in form.slots"
                            :key="`${index}-${slot}`"
                            class="flex items-center gap-2"
                        >
                            <Input
                                :id="
                                    index === 0 ? 'fund-cycle-slots' : undefined
                                "
                                v-model="form.slots[index]"
                                :placeholder="
                                    index === 0
                                        ? formatSlotLabel(form.start_date) ||
                                          'January 2026'
                                        : 'Additional slot'
                                "
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                @click="removeSlot(index)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            class="w-full"
                            @click="addSlot"
                        >
                            <Plus class="size-4" />
                            Add Slot
                        </Button>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Slots auto-fill from the start date through the lock
                        date. You can add more slots with the plus button or
                        remove any slot with the cross button.
                    </p>
                    <InputError
                        :message="form.errors.slots || form.errors['slots.0']"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="fund-cycle-notes">Notes</Label>
                    <Input
                        id="fund-cycle-notes"
                        v-model="form.notes"
                        placeholder="Optional internal note"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="closeDialog"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Save Changes' : 'Add Fund Cycle' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
