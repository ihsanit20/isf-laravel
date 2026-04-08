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

type ReviewMode = 'approve' | 'reject' | 'exit';
type ReviewStatus = 'approved' | 'rejected' | 'exited';

type ReviewableMember = {
    id: number;
    full_name: string;
    status: 'pending' | 'approved' | 'rejected' | 'exited';
    registration_fee_amount: number;
    registration_fee_payment_method_label: string | null;
    registration_fee_reference_no: string | null;
    registration_fee_proof_url: string | null;
};

type Props = {
    member?: ReviewableMember | null;
    mode: ReviewMode;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const reviewStatus = (): ReviewStatus =>
    props.mode === 'approve'
        ? 'approved'
        : props.mode === 'reject'
          ? 'rejected'
          : 'exited';

const form = useForm({
    status: reviewStatus(),
    rejection_note: '',
});

const isRejecting = computed(() => props.mode === 'reject');
const isExiting = computed(() => props.mode === 'exit');

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
                    {{
                        isRejecting
                            ? 'Reject Member'
                            : isExiting
                              ? 'Exit Member'
                              : 'Approve Member'
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isRejecting
                            ? `Record a rejection note for ${member?.full_name}.`
                            : isExiting
                              ? `Confirm that ${member?.full_name} has exited the fund.`
                              : `Confirm approval for ${member?.full_name} after reviewing the membership details and registration fee proof.`
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div
                    v-if="!isRejecting && member"
                    class="rounded-2xl border border-border/70 bg-muted/20 px-4 py-3 text-sm"
                >
                    <p class="font-medium text-foreground">
                        Registration fee review
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        {{ member.registration_fee_amount.toLocaleString() }}
                        BDT via
                        {{
                            member.registration_fee_payment_method_label ||
                            'Unknown method'
                        }}
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        Reference:
                        {{
                            member.registration_fee_reference_no ||
                            'Not provided'
                        }}
                    </p>
                    <a
                        v-if="member.registration_fee_proof_url"
                        :href="member.registration_fee_proof_url"
                        class="mt-2 inline-flex font-medium text-foreground underline underline-offset-4"
                        target="_blank"
                        rel="noreferrer"
                    >
                        View uploaded proof
                    </a>
                </div>

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
                        {{
                            isRejecting
                                ? 'Reject Member'
                                : isExiting
                                  ? 'Mark as Exited'
                                  : 'Approve Member'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
