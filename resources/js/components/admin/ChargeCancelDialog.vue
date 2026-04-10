<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type CancelableCharge = {
    id: number;
    amount: number;
    category: {
        title: string | null;
    };
    member: {
        full_name: string | null;
    };
};

type Props = {
    charge?: CancelableCharge | null;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const form = useForm({});

const closeDialog = () => {
    isOpen.value = false;
    form.clearErrors();
};

const submit = () => {
    if (!props.charge) {
        return;
    }

    form.patch(`/admin/charges/${props.charge.id}/cancel`, {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

watch(
    () => [isOpen.value, props.charge?.id],
    ([open]) => {
        if (open) {
            form.clearErrors();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Cancel Charge</DialogTitle>
                <DialogDescription>
                    {{ charge?.category.title || 'This charge' }} for
                    {{ charge?.member.full_name || 'the selected member' }} will be cancelled.
                    Any posted allocation against this charge will return to the deposit pool.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <Button type="button" variant="secondary" @click="closeDialog">
                    Keep Charge
                </Button>
                <Button type="button" variant="destructive" @click="submit" :disabled="form.processing">
                    Cancel Charge
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>