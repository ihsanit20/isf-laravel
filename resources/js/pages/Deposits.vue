<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Plus } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type DepositStatus = 'pending' | 'verified' | 'rejected';

type ChargeAllocation = {
    id: number;
    member_name: string | null;
    charge_title: string | null;
    amount: number;
    confirmed_at: string | null;
    reversed_at: string | null;
};

type DepositItem = {
    id: number;
    amount: number;
    payment_method: string;
    payment_method_label: string;
    reference_no: string | null;
    deposit_date: string | null;
    proof_url: string | null;
    notes: string | null;
    status: DepositStatus;
    verified_at: string | null;
    rejection_reason: string | null;
};

type DepositSummary = {
    total_deposit_amount: number;
    total_verified_amount: number;
    total_charge_allocated_amount: number;
    total_allocated_amount: number;
    total_allocatable_amount: number;
    total_deposit_count: number;
    can_allocate: boolean;
};

type Props = {
    summary: DepositSummary;
    deposits: DepositItem[];
    chargeAllocations: ChargeAllocation[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Deposits',
                href: '/my-deposits',
            },
        ],
    },
});

defineProps<Props>();

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
</script>

<template>
    <Head title="My Deposits" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-amber-50 p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-2xl">
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Total Deposited
                    </p>
                    <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                        {{ money(summary.total_deposit_amount) }}
                    </h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        Deposits stay independent from charge settlement. Submit
                        deposits first, then settle any pending charges from the
                        verified deposit pool when needed.
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <Button as-child class="shrink-0">
                        <Link href="/my-deposits/create">
                            <Plus class="size-4" />
                            Submit Deposit
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <p class="text-xs text-muted-foreground">Verified Deposits</p>
                <p class="mt-2 text-xl font-semibold text-foreground">
                    {{ money(summary.total_verified_amount) }}
                </p>
            </div>
            <div
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <p class="text-xs text-muted-foreground">Allocated Total</p>
                <p class="mt-2 text-xl font-semibold text-foreground">
                    {{ money(summary.total_allocated_amount) }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Charges only from your verified deposit pool
                </p>
            </div>
            <div
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <p class="text-xs text-muted-foreground">
                    Available To Allocate
                </p>
                <p class="mt-2 text-xl font-semibold text-foreground">
                    {{ money(summary.total_allocatable_amount) }}
                </p>
            </div>
            <div
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <p class="text-xs text-muted-foreground">Deposit Count</p>
                <p class="mt-2 text-xl font-semibold text-foreground">
                    {{ summary.total_deposit_count }}
                </p>
            </div>
        </section>

        <section v-if="deposits.length > 0" class="space-y-4">
            <div>
                <h2
                    class="text-lg font-semibold tracking-tight text-foreground"
                >
                    Deposit History
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Deposits stay independent from allocation history.
                </p>
            </div>

            <div
                class="overflow-hidden rounded-[28px] border border-sidebar-border/70 bg-background shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                    >
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Deposit</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr
                                v-for="deposit in deposits"
                                :key="deposit.id"
                                class="align-top"
                            >
                                <td class="px-4 py-4">
                                    <div class="font-medium text-foreground">
                                        Deposit #{{ deposit.id }}
                                    </div>
                                    <div
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ money(deposit.amount) }}
                                    </div>
                                    <div
                                        v-if="deposit.rejection_reason"
                                        class="mt-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700"
                                    >
                                        {{ deposit.rejection_reason }}
                                    </div>
                                </td>
                                <td class="px-4 py-4">
                                    <Badge
                                        :variant="statusVariant(deposit.status)"
                                    >
                                        {{ statusLabel(deposit.status) }}
                                    </Badge>
                                    <div
                                        class="mt-2 flex items-start gap-2 text-sm text-muted-foreground"
                                    >
                                        <CalendarDays
                                            class="mt-0.5 size-4 shrink-0"
                                        />
                                        <span>{{
                                            deposit.verified_at ||
                                            'Pending review'
                                        }}</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section
            v-else
            class="rounded-[28px] border border-dashed border-sidebar-border/80 bg-background p-10 text-center shadow-sm"
        >
            <div class="mx-auto max-w-md">
                <p
                    class="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase"
                >
                    No Deposits Yet
                </p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                    No deposit submissions found
                </h2>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Submit your first bank deposit with proof, then use the
                    verified balance to settle pending charges.
                </p>
                <Button as-child class="mt-6">
                    <Link href="/my-deposits/create">
                        <Plus class="size-4" />
                        Submit Deposit
                    </Link>
                </Button>
            </div>
        </section>
    </div>
</template>
