<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type ReviewMode = 'post' | 'waive' | 'cancel';
type ReviewStatus = 'posted' | 'waived' | 'cancelled';

type ReviewableCharge = {
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
    charge?: ReviewableCharge | null;
    mode: ReviewMode;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const reviewStatus = (): ReviewStatus => {
    if (props.mode === 'post') {
        return 'posted';
    }

    if (props.mode === 'waive') {
        return 'waived';
    }

    return 'cancelled';
};

const form = useForm({
    status: reviewStatus(),
});

const actionLabel = computed(() => {
    if (props.mode === 'post') {
        return 'Post Charge';
    }

    if (props.mode === 'waive') {
        return 'Waive Charge';
    }

    return 'Cancel Charge';
});

const resetFormState = () => {
    form.defaults({
        status: reviewStatus(),
    });
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    isOpen.value = false;
    resetFormState();
};

const submit = () => {
    if (!props.charge) {
        return;
    }

    form.patch(`/admin/charges/${props.charge.id}/review`, {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

watch(
    () => [isOpen.value, props.charge?.id, props.mode],
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
                <DialogTitle>{{ actionLabel }}</DialogTitle>
                <DialogDescription>
                    {{ charge?.category.title || 'This charge' }} for
                    {{ charge?.member.full_name || 'the selected member' }} will
                    be updated to {{ form.status }}.
                </DialogDescription>
            </DialogHeader>

            <DialogFooter class="gap-2">
                <Button type="button" variant="secondary" @click="closeDialog">
                    Keep Pending
                </Button>
                <Button
                    type="button"
                    @click="submit"
                    :disabled="form.processing"
                >
                    {{ actionLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
