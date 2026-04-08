<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    FileBadge2,
    Plus,
    ReceiptText,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type DepositStatus = 'pending' | 'verified' | 'rejected';

type DepositAllocation = {
    id: number;
    member_name: string | null;
    allocation_month: string | null;
    units: number;
    allocated_amount: number;
    confirmed_at: string | null;
};

type DepositItem = {
    id: number;
    amount: number;
    allocated_amount: number;
    remaining_amount: number;
    payment_method: string;
    payment_method_label: string;
    reference_no: string | null;
    deposit_date: string | null;
    proof_url: string | null;
    notes: string | null;
    status: DepositStatus;
    verified_at: string | null;
    rejection_reason: string | null;
    can_allocate: boolean;
    allocations: DepositAllocation[];
};

type Props = {
    deposits: DepositItem[];
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
                    <h1 class="text-3xl font-semibold tracking-tight">
                        My Deposits
                    </h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        Submit a single bank deposit, wait for admin
                        verification, then allocate the verified amount across
                        your approved members.
                    </p>
                </div>

                <Button as-child class="shrink-0">
                    <Link href="/my-deposits/create">
                        <Plus class="size-4" />
                        Submit Deposit
                    </Link>
                </Button>
            </div>
        </section>

        <section v-if="deposits.length > 0" class="grid gap-4 xl:grid-cols-2">
            <article
                v-for="deposit in deposits"
                :key="deposit.id"
                class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Deposit #{{ deposit.id }}
                        </p>
                        <h2 class="mt-2 text-2xl font-semibold tracking-tight">
                            {{ money(deposit.amount) }}
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ deposit.payment_method_label }}
                            <span v-if="deposit.deposit_date">
                                • {{ deposit.deposit_date }}
                            </span>
                        </p>
                    </div>

                    <Badge :variant="statusVariant(deposit.status)">
                        {{ statusLabel(deposit.status) }}
                    </Badge>
                </div>

                <div class="mt-5 grid gap-3 md:grid-cols-2">
                    <div class="rounded-2xl bg-muted/40 px-4 py-4">
                        <p class="text-xs text-muted-foreground">
                            Allocated Amount
                        </p>
                        <p class="mt-2 text-lg font-semibold text-foreground">
                            {{ money(deposit.allocated_amount) }}
                        </p>
                    </div>
                    <div class="rounded-2xl bg-muted/40 px-4 py-4">
                        <p class="text-xs text-muted-foreground">
                            Remaining Amount
                        </p>
                        <p class="mt-2 text-lg font-semibold text-foreground">
                            {{ money(deposit.remaining_amount) }}
                        </p>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 text-sm text-muted-foreground">
                    <div class="flex items-center gap-2">
                        <ReceiptText class="size-4" />
                        <span>
                            Reference:
                            {{ deposit.reference_no || 'Not provided' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <CalendarDays class="size-4" />
                        <span>
                            Verified At:
                            {{ deposit.verified_at || 'Pending review' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <FileBadge2 class="size-4" />
                        <a
                            v-if="deposit.proof_url"
                            :href="deposit.proof_url"
                            class="font-medium text-foreground underline underline-offset-4"
                            target="_blank"
                            rel="noreferrer"
                        >
                            View uploaded proof
                        </a>
                        <span v-else>No proof uploaded</span>
                    </div>
                </div>

                <p
                    v-if="deposit.notes"
                    class="mt-4 text-sm leading-6 text-muted-foreground"
                >
                    {{ deposit.notes }}
                </p>

                <p
                    v-if="deposit.rejection_reason"
                    class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                >
                    {{ deposit.rejection_reason }}
                </p>

                <div class="mt-5 flex flex-wrap gap-3">
                    <Button v-if="deposit.can_allocate" as-child>
                        <Link :href="`/my-deposits/${deposit.id}/allocate`">
                            <ArrowRight class="size-4" />
                            Allocate Deposit
                        </Link>
                    </Button>
                    <Button
                        v-else-if="deposit.status === 'verified'"
                        variant="outline"
                        disabled
                    >
                        Fully Allocated
                    </Button>
                </div>

                <div v-if="deposit.allocations.length > 0" class="mt-6">
                    <h3 class="text-sm font-semibold text-foreground">
                        Allocation History
                    </h3>
                    <div class="mt-3 space-y-2">
                        <div
                            v-for="allocation in deposit.allocations"
                            :key="allocation.id"
                            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-border/70 bg-muted/20 px-4 py-3"
                        >
                            <div>
                                <p class="font-medium text-foreground">
                                    {{
                                        allocation.member_name ||
                                        'Unknown member'
                                    }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ allocation.allocation_month || '-' }} •
                                    {{ allocation.units }} unit{{
                                        allocation.units > 1 ? 's' : ''
                                    }}
                                </p>
                            </div>

                            <div class="text-right">
                                <p class="font-medium text-foreground">
                                    {{ money(allocation.allocated_amount) }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ allocation.confirmed_at || 'Confirmed' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
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
                    Submit your first bank deposit with proof, then allocate the
                    verified amount to your approved members.
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
