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

type EditableBankWithdrawal = {
    id: number;
    withdrawal_date: string;
    amount: number;
    description: string | null;
    reference_no: string | null;
};

type CycleWithdrawalBudget = {
    allocated_amount: number;
    withdrawn_amount: number;
    remaining_amount: number;
};

type Props = {
    eventId: number;
    mode: 'create' | 'edit';
    bankWithdrawal?: EditableBankWithdrawal | null;
    cycleWithdrawalBudget: CycleWithdrawalBudget;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const today = new Date().toISOString().slice(0, 10);

const isEditing = computed(
    () => props.mode === 'edit' && !!props.bankWithdrawal,
);

const form = useForm<{
    withdrawal_date: string;
    amount: string;
    description: string;
    reference_no: string;
}>({
    withdrawal_date: today,
    amount: '',
    description: '',
    reference_no: '',
});

const resetFormState = () => {
    const values =
        isEditing.value && props.bankWithdrawal
            ? {
                  withdrawal_date: props.bankWithdrawal.withdrawal_date,
                  amount: String(props.bankWithdrawal.amount),
                  description: props.bankWithdrawal.description ?? '',
                  reference_no: props.bankWithdrawal.reference_no ?? '',
              }
            : {
                  withdrawal_date: today,
                  amount: '',
                  description: '',
                  reference_no: '',
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
    const payload = {
        ...form.data(),
        amount: Number(form.amount),
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    };

    if (isEditing.value && props.bankWithdrawal) {
        form.transform(() => ({
            ...payload,
            _method: 'put',
        })).post(
            `/admin/events/${props.eventId}/bank-withdrawals/${props.bankWithdrawal.id}`,
            options,
        );

        return;
    }

    form.transform(() => payload).post(
        `/admin/events/${props.eventId}/bank-withdrawals`,
        options,
    );
};

watch(
    () => [isOpen.value, props.mode, props.bankWithdrawal?.id],
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
                <DialogTitle>
                    {{
                        isEditing
                            ? 'Edit Bank Withdrawal'
                            : 'Record Bank Withdrawal'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Log cash taken from the joint bank account for this event.
                    This reduces Deposits → Current Balance immediately. Total
                    withdrawals for this fund cycle cannot exceed member
                    allocations (remaining budget:
                    {{ cycleWithdrawalBudget.remaining_amount.toLocaleString() }}
                    BDT).
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="bank-withdrawal-date">Withdrawal Date</Label>
                        <Input
                            id="bank-withdrawal-date"
                            v-model="form.withdrawal_date"
                            type="date"
                        />
                        <InputError :message="form.errors.withdrawal_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bank-withdrawal-amount">Amount (BDT)</Label>
                        <Input
                            id="bank-withdrawal-amount"
                            v-model="form.amount"
                            type="number"
                            min="1"
                            placeholder="10000"
                        />
                        <InputError :message="form.errors.amount" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="bank-withdrawal-reference">Reference No.</Label>
                    <Input
                        id="bank-withdrawal-reference"
                        v-model="form.reference_no"
                        type="text"
                        placeholder="Check / transfer reference"
                    />
                    <p class="text-xs text-muted-foreground">
                        Use the same reference when one check covers multiple
                        events.
                    </p>
                    <InputError :message="form.errors.reference_no" />
                </div>

                <div class="grid gap-2">
                    <Label for="bank-withdrawal-description">Description</Label>
                    <textarea
                        id="bank-withdrawal-description"
                        v-model="form.description"
                        rows="3"
                        class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="e.g. Investment float for procurement"
                    />
                    <InputError :message="form.errors.description" />
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
                        {{ isEditing ? 'Save Changes' : 'Record Withdrawal' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
