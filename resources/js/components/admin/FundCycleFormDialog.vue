<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
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

const form = useForm<{
    name: string;
    status: string;
    start_date: string;
    lock_date: string;
    maturity_date: string;
    settlement_date: string;
    slots_text: string;
    notes: string;
}>({
    name: '',
    status: props.statuses[0] ?? 'draft',
    start_date: '',
    lock_date: '',
    maturity_date: '',
    settlement_date: '',
    slots_text: '',
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
                  slots_text: props.fundCycle.slots.join('\n'),
                  notes: props.fundCycle.notes ?? '',
              }
            : {
                  name: '',
                  status: props.statuses[0] ?? 'draft',
                  start_date: '',
                  lock_date: '',
                  maturity_date: '',
                  settlement_date: '',
                  slots_text: '',
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
    const slots = form.slots_text
        .split(/\r?\n|,/)
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
                    <textarea
                        id="fund-cycle-slots"
                        v-model="form.slots_text"
                        rows="4"
                        class="flex min-h-24 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        placeholder="January 2026&#10;February 2026&#10;March 2026"
                    />
                    <p class="text-xs text-muted-foreground">
                        Add one slot per line. These values will be stored in
                        the cycle JSON field and used during member allocation.
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
