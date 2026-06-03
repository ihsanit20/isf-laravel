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

export type EventOrderStatusTarget = {
    id: number;
    order_number: string;
    status: string;
    due_amount: string;
};

type StatusOption = {
    value: string;
    label: string;
};

type Props = {
    eventId: number;
    order: EventOrderStatusTarget | null;
    statusOptions: StatusOption[];
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const form = useForm({
    status: '',
    note: '',
    allow_delivered_with_due: false,
});

const showDeliveredOverride = computed(
    () =>
        form.status === 'delivered' &&
        props.order !== null &&
        Number.parseFloat(props.order.due_amount) > 0,
);

const resetFormState = () => {
    if (!props.order) {
        return;
    }

    form.defaults({
        status: props.order.status,
        note: '',
        allow_delivered_with_due: false,
    });
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    isOpen.value = false;
    resetFormState();
};

watch(
    () => [isOpen.value, props.order?.id, props.order?.status] as const,
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

    form.patch(
        `/admin/events/${props.eventId}/orders/${props.order.id}/status`,
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
                <DialogTitle>Update status</DialogTitle>
                <DialogDescription v-if="order">
                    Order {{ order.order_number }} — choose the new status and
                    add a note if needed.
                </DialogDescription>
            </DialogHeader>

            <form
                v-if="order"
                :id="`event-order-status-form-${order.id}`"
                class="space-y-3"
                @submit.prevent="submit"
            >
                <div class="space-y-1.5">
                    <Label :for="`status-${order.id}`">Status</Label>
                    <select
                        :id="`status-${order.id}`"
                        v-model="form.status"
                        class="h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
                <div class="space-y-1.5">
                    <Label :for="`status-note-${order.id}`">Note</Label>
                    <Input
                        :id="`status-note-${order.id}`"
                        v-model="form.note"
                        placeholder="Reason or delivery note"
                    />
                    <InputError :message="form.errors.note" />
                </div>
                <label
                    v-if="showDeliveredOverride"
                    class="flex items-center gap-2 text-sm"
                >
                    <input
                        v-model="form.allow_delivered_with_due"
                        type="checkbox"
                        class="size-4 rounded border-input"
                    />
                    Mark delivered even with due balance
                </label>
            </form>

            <DialogFooter class="pt-2">
                <Button type="button" variant="outline" @click="closeDialog">
                    Cancel
                </Button>
                <Button
                    v-if="order"
                    type="submit"
                    :form="`event-order-status-form-${order.id}`"
                    :disabled="form.processing"
                >
                    Save status
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
