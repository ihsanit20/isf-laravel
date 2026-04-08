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

type ReviewMode = 'approve' | 'reject';
type ReviewStatus = 'approved' | 'rejected';

type ReviewableMember = {
    id: number;
    full_name: string;
    status: 'pending' | 'approved' | 'rejected' | 'exited';
};

type Props = {
    member?: ReviewableMember | null;
    mode: ReviewMode;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const reviewStatus = (): ReviewStatus =>
    props.mode === 'approve' ? 'approved' : 'rejected';

const form = useForm({
    status: reviewStatus(),
    rejection_note: '',
});

const isRejecting = computed(() => props.mode === 'reject');

const resetFormState = () => {
    form.defaults({
        status: reviewStatus(),
        rejection_note: '',
    });
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    isOpen.value = false;
    resetFormState();
};

const submit = () => {
    if (!props.member) {
        return;
    }

    form.patch(`/admin/members/${props.member.id}/review`, {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

watch(
    () => [isOpen.value, props.member?.id, props.mode],
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
                    {{ isRejecting ? 'Reject Member' : 'Approve Member' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isRejecting
                            ? `Record a rejection note for ${member?.full_name}.`
                            : `Confirm approval for ${member?.full_name}.`
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div v-if="isRejecting" class="grid gap-2">
                    <Label for="rejection-note">Rejection note</Label>
                    <Input
                        id="rejection-note"
                        v-model="form.rejection_note"
                        placeholder="Enter the reason for rejection"
                    />
                    <InputError :message="form.errors.rejection_note" />
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
                        {{ isRejecting ? 'Reject Member' : 'Approve Member' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
