<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, FileBadge2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DepositReviewDialog from '@/components/admin/DepositReviewDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type DepositStatus = 'pending' | 'verified' | 'rejected';

type AdminDeposit = {
    id: number;
    amount: number;
    payment_method_label: string;
    reference_no: string | null;
    deposit_date: string | null;
    proof_url: string | null;
    notes: string | null;
    status: DepositStatus;
    verified_at: string | null;
    rejection_reason: string | null;
    user: {
        name: string | null;
        email: string | null;
    };
    verifier: string | null;
};

type Props = {
    deposits: AdminDeposit[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Deposit Reviews',
                href: '/admin/deposits',
            },
        ],
    },
});

defineProps<Props>();

const selectedDeposit = ref<AdminDeposit | null>(null);
const isVerifyDialogOpen = ref(false);
const isRejectDialogOpen = ref(false);

const reviewableDeposit = computed(() => {
    if (!selectedDeposit.value) {
        return null;
    }

    return {
        id: selectedDeposit.value.id,
        amount: selectedDeposit.value.amount,
        user: selectedDeposit.value.user,
    };
});

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const statusLabel = (status: DepositStatus): string =>
    status.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const statusVariant = (
    status: DepositStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'verified') {
        return 'default';
    }

    if (status === 'rejected') {
        return 'destructive';
    }

    return 'secondary';
};

const openVerifyDialog = (deposit: AdminDeposit) => {
    selectedDeposit.value = deposit;
    isVerifyDialogOpen.value = true;
};

const openRejectDialog = (deposit: AdminDeposit) => {
    selectedDeposit.value = deposit;
    isRejectDialogOpen.value = true;
};
</script>

<template>
    <Head title="Deposit Reviews" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="max-w-2xl">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Deposit Reviews
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Verify or reject uploaded deposit proof. Member allocation
                    stays entirely in the user's control after verification.
                </p>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                >
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">User</th>
                            <th class="px-4 py-3 font-medium">Deposit</th>
                            <th class="px-4 py-3 font-medium">Reference</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Reviewed By</th>
                            <th class="px-4 py-3 font-medium">Proof</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="deposit in deposits" :key="deposit.id">
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ deposit.user.name || 'Unknown account' }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ deposit.user.email || '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>{{ money(deposit.amount) }}</div>
                                <div class="text-xs">
                                    {{ deposit.payment_method_label }}
                                    <span v-if="deposit.deposit_date">
                                        • {{ deposit.deposit_date }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>{{ deposit.reference_no || '-' }}</div>
                                <div v-if="deposit.notes" class="text-xs">
                                    {{ deposit.notes }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="statusVariant(deposit.status)">
                                    {{ statusLabel(deposit.status) }}
                                </Badge>
                                <p
                                    v-if="deposit.rejection_reason"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ deposit.rejection_reason }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>{{ deposit.verifier || '-' }}</div>
                                <div class="text-xs">
                                    {{ deposit.verified_at || '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <a
                                    v-if="deposit.proof_url"
                                    :href="deposit.proof_url"
                                    class="inline-flex items-center gap-2 font-medium text-foreground underline underline-offset-4"
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <FileBadge2 class="size-4" />
                                    View proof
                                </a>
                                <span v-else>-</span>
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    v-if="deposit.status === 'pending'"
                                    class="flex flex-wrap gap-2"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openVerifyDialog(deposit)"
                                    >
                                        <Check class="size-4" />
                                        Verify
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openRejectDialog(deposit)"
                                    >
                                        <X class="size-4" />
                                        Reject
                                    </Button>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    Reviewed
                                </span>
                            </td>
                        </tr>
                        <tr v-if="deposits.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No deposits found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <DepositReviewDialog
            v-model:isOpen="isVerifyDialogOpen"
            mode="verify"
            :deposit="reviewableDeposit"
        />

        <DepositReviewDialog
            v-model:isOpen="isRejectDialogOpen"
            mode="reject"
            :deposit="reviewableDeposit"
        />
    </div>
</template>
