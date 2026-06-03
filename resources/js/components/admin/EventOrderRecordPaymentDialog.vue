<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
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

export type EventOrderPaymentTarget = {
    id: number;
    order_number: string;
    due_amount: string;
};

type PaymentMethodOption = {
    value: string;
    label: string;
};

type Props = {
    eventId: number;
    order: EventOrderPaymentTarget | null;
    paymentMethodOptions: PaymentMethodOption[];
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const form = useForm({
    amount: '',
    payment_method: 'cash',
    transaction_reference: '',
    note: '',
});

const resetFormState = () => {
    if (!props.order) {
        return;
    }

    form.defaults({
        amount: props.order.due_amount,
        payment_method: 'cash',
        transaction_reference: '',
        note: '',
    });
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    isOpen.value = false;
    resetFormState();
};

watch(
    () => [isOpen.value, props.order?.id, props.order?.due_amount] as const,
    ([open]) => {
        if (open) {
            resetFormState();
        }
    },
);

const submit = () => {
    if (!props.order) {
        return;
    }

    form.post(
        `/admin/events/${props.eventId}/orders/${props.order.id}/payments`,
        {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        },
    );
};
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Record manual payment</DialogTitle>
                <DialogDescription v-if="order">
                    Order {{ order.order_number }} — cash or bank at pickup.
                    Verify the payment from the order details after recording.
                </DialogDescription>
            </DialogHeader>

            <form
                v-if="order"
                :id="`event-order-payment-form-${order.id}`"
                class="space-y-3"
                @submit.prevent="submit"
            >
                <div class="space-y-1.5">
                    <Label :for="`payment-amount-${order.id}`">Amount</Label>
                    <Input
                        :id="`payment-amount-${order.id}`"
                        v-model="form.amount"
                        type="number"
                        step="0.01"
                        min="0.01"
                    />
                    <InputError :message="form.errors.amount" />
                </div>
                <div class="space-y-1.5">
                    <Label :for="`payment-method-${order.id}`">Method</Label>
                    <select
                        :id="`payment-method-${order.id}`"
                        v-model="form.payment_method"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option
                            v-for="method in paymentMethodOptions"
                            :key="method.value"
                            :value="method.value"
                        >
                            {{ method.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.payment_method" />
                </div>
                <div class="space-y-1.5">
                    <Label :for="`payment-ref-${order.id}`">Reference</Label>
                    <Input
                        :id="`payment-ref-${order.id}`"
                        v-model="form.transaction_reference"
                    />
                </div>
                <div class="space-y-1.5">
                    <Label :for="`payment-note-${order.id}`">Note</Label>
                    <Input
                        :id="`payment-note-${order.id}`"
                        v-model="form.note"
                    />
                </div>
            </form>

            <DialogFooter class="pt-2">
                <Button type="button" variant="outline" @click="closeDialog">
                    Cancel
                </Button>
                <Button
                    v-if="order"
                    type="submit"
                    :form="`event-order-payment-form-${order.id}`"
                    :disabled="form.processing"
                >
                    Record payment
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
