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

type FundCycleItem = {
    id: number;
    name: string;
    slots: string[];
};

type EligibleMember = {
    id: number;
    full_name: string;
    units: number;
};

type Props = {
    fundCycle?: FundCycleItem | null;
    eligibleMembers: EligibleMember[];
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const form = useForm<{
    member_id: string;
    slot_key: string;
    amount: string;
    notes: string;
}>({
    member_id: '',
    slot_key: '',
    amount: '',
    notes: '',
});

const memberLabel = computed<Record<string, string>>(() =>
    Object.fromEntries(
        props.eligibleMembers.map((member) => [
            String(member.id),
            `${member.full_name} (${member.units} unit${member.units > 1 ? 's' : ''})`,
        ]),
    ),
);

const resetFormState = () => {
    form.defaults({
        member_id: '',
        slot_key: '',
        amount: '',
        notes: '',
    });
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    isOpen.value = false;
    resetFormState();
};

const submit = () => {
    if (!props.fundCycle) {
        return;
    }

    form.transform(() => ({
        member_id: Number(form.member_id),
        slot_key: form.slot_key,
        amount: Number(form.amount),
        notes: form.notes || null,
    })).post(`/admin/fund-cycles/${props.fundCycle.id}/allocations`, {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

watch(
    () => [isOpen.value, props.fundCycle?.id, props.eligibleMembers.length],
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
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Add Allocation</DialogTitle>
                <DialogDescription>
                    Assign a verified deposit amount from the global pool into
                    this fund cycle for one approved member.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="allocation-member">Member</Label>
                    <Select v-model="form.member_id">
                        <SelectTrigger id="allocation-member" class="w-full">
                            <SelectValue placeholder="Select member" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="member in eligibleMembers"
                                :key="member.id"
                                :value="String(member.id)"
                            >
                                {{ memberLabel[String(member.id)] }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.member_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="allocation-slot">Slot</Label>
                    <Select v-model="form.slot_key">
                        <SelectTrigger id="allocation-slot" class="w-full">
                            <SelectValue placeholder="Select slot" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="slot in fundCycle?.slots ?? []"
                                :key="slot"
                                :value="slot"
                            >
                                {{ slot }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.slot_key" />
                </div>

                <div class="grid gap-2">
                    <Label for="allocation-amount">Amount</Label>
                    <Input
                        id="allocation-amount"
                        v-model="form.amount"
                        type="number"
                        min="1"
                        placeholder="1000"
                    />
                    <InputError :message="form.errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="allocation-notes">Notes</Label>
                    <Input
                        id="allocation-notes"
                        v-model="form.notes"
                        placeholder="Optional allocation note"
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
                    <Button
                        type="submit"
                        :disabled="
                            form.processing ||
                            !fundCycle ||
                            fundCycle.slots.length === 0
                        "
                    >
                        Save Allocation
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
