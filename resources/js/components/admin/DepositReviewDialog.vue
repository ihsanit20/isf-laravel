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

type ReviewMode = 'verify' | 'reject';
type ReviewStatus = 'verified' | 'rejected';

type ReviewableDeposit = {
    id: number;
    amount: number;
    user: {
        name: string | null;
    };
};

type Props = {
    deposit?: ReviewableDeposit | null;
    mode: ReviewMode;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const reviewStatus = (): ReviewStatus =>
    props.mode === 'verify' ? 'verified' : 'rejected';

const form = useForm({
    status: reviewStatus(),
    rejection_reason: '',
});

const isRejecting = computed(() => props.mode === 'reject');

const resetFormState = () => {
    form.defaults({
        status: reviewStatus(),
        rejection_reason: '',
    });
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    isOpen.value = false;
    resetFormState();
};

const submit = () => {
    if (!props.deposit) {
        return;
    }

    form.patch(`/admin/deposits/${props.deposit.id}/review`, {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

watch(
    () => [isOpen.value, props.deposit?.id, props.mode],
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
                    {{ isRejecting ? 'Reject Deposit' : 'Verify Deposit' }}
                </DialogTitle>
                <DialogDescription>
                    <template v-if="isRejecting">
                        Record a rejection note for the submitted deposit.
                    </template>
                    <template v-else>
                        Confirm the verified deposit for
                        {{ deposit?.user.name || 'this user' }}.
                    </template>
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div v-if="isRejecting" class="grid gap-2">
                    <Label for="deposit-rejection-reason"
                        >Rejection reason</Label
                    >
                    <Input
                        id="deposit-rejection-reason"
                        v-model="form.rejection_reason"
                        placeholder="Explain why this deposit cannot be verified"
                    />
                    <InputError :message="form.errors.rejection_reason" />
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
                        {{ isRejecting ? 'Reject Deposit' : 'Verify Deposit' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
