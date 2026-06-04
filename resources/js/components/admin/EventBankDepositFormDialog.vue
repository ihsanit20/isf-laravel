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

type EditableBankDeposit = {
    id: number;
    deposit_date: string;
    amount: number;
    description: string | null;
    reference_no: string | null;
};

type Props = {
    eventId: number;
    mode: 'create' | 'edit';
    bankDeposit?: EditableBankDeposit | null;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const today = new Date().toISOString().slice(0, 10);

const isEditing = computed(() => props.mode === 'edit' && !!props.bankDeposit);

const form = useForm<{
    deposit_date: string;
    amount: string;
    description: string;
    reference_no: string;
}>({
    deposit_date: today,
    amount: '',
    description: '',
    reference_no: '',
});

const resetFormState = () => {
    const values =
        isEditing.value && props.bankDeposit
            ? {
                  deposit_date: props.bankDeposit.deposit_date,
                  amount: String(props.bankDeposit.amount),
                  description: props.bankDeposit.description ?? '',
                  reference_no: props.bankDeposit.reference_no ?? '',
              }
            : {
                  deposit_date: today,
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

    if (isEditing.value && props.bankDeposit) {
        form.transform(() => ({
            ...payload,
            _method: 'put',
        })).post(
            `/admin/events/${props.eventId}/bank-deposits/${props.bankDeposit.id}`,
            options,
        );

        return;
    }

    form.transform(() => payload).post(
        `/admin/events/${props.eventId}/bank-deposits`,
        options,
    );
};

watch(
    () => [isOpen.value, props.mode, props.bankDeposit?.id],
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
                            ? 'Edit Bank Deposit'
                            : 'Record Bank Deposit'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Log cash returned to the joint bank account from this event.
                    This increases Deposits → Current Balance.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="bank-deposit-date">Deposit Date</Label>
                        <Input
                            id="bank-deposit-date"
                            v-model="form.deposit_date"
                            type="date"
                        />
                        <InputError :message="form.errors.deposit_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="bank-deposit-amount">Amount (BDT)</Label>
                        <Input
                            id="bank-deposit-amount"
                            v-model="form.amount"
                            type="number"
                            min="1"
                            placeholder="10000"
                        />
                        <InputError :message="form.errors.amount" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="bank-deposit-reference">Reference No.</Label>
                    <Input
                        id="bank-deposit-reference"
                        v-model="form.reference_no"
                        type="text"
                        placeholder="Deposit slip / transfer reference"
                    />
                    <InputError :message="form.errors.reference_no" />
                </div>

                <div class="grid gap-2">
                    <Label for="bank-deposit-description">Description</Label>
                    <textarea
                        id="bank-deposit-description"
                        v-model="form.description"
                        rows="3"
                        class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="e.g. Event closing balance deposited"
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
                        {{ isEditing ? 'Save Changes' : 'Record Deposit' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
