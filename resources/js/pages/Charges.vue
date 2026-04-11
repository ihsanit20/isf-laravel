<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BadgeDollarSign,
    CalendarDays,
    CheckCircle2,
    Clock3,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

type ChargeStatus = 'pending' | 'posted' | 'waived' | 'cancelled';

type ChargeItem = {
    id: number;
    member_name: string | null;
    charge_title: string | null;
    charge_code: string | null;
    amount: number;
    status: ChargeStatus;
    effective_at: string | null;
    allocated_amount: number;
    last_confirmed_at: string | null;
    last_reversed_at: string | null;
};

type Props = {
    summary: {
        total_charges: number;
        total_charge_amount: number;
        pending_charges: number;
        settled_charges: number;
    };
    allocationSummary: {
        active_charge_allocations: number;
    };
    charges: ChargeItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Charges',
                href: '/my-charges',
            },
        ],
    },
});

defineProps<Props>();

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const statusVariant = (
    status: ChargeStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'posted' || status === 'waived') {
        return 'default';
    }

    if (status === 'cancelled') {
        return 'destructive';
    }

    return 'secondary';
};

const statusLabel = (status: ChargeStatus): string =>
    status.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());
</script>

<template>
    <Head title="My Charges" />

    <div class="flex h-full flex-1 flex-col gap-6 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-rose-50 p-6 shadow-sm"
        >
            <div class="max-w-3xl">
                <p
                    class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                >
                    My Charges
                </p>
                <h1 class="mt-2 text-3xl font-semibold tracking-tight">
                    Charges across all your members
                </h1>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Review charge amounts, charge status, and whether those
                    charges were settled from your verified pool.
                </p>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <BadgeDollarSign class="size-5 text-muted-foreground" />
                <p class="mt-4 text-xs text-muted-foreground">
                    My Total Charges
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ summary.total_charges.toLocaleString() }}
                </p>
            </article>

            <article
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <CalendarDays class="size-5 text-muted-foreground" />
                <p class="mt-4 text-xs text-muted-foreground">
                    My Charge Amount
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ money(summary.total_charge_amount) }}
                </p>
            </article>

            <article
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <Clock3 class="size-5 text-muted-foreground" />
                <p class="mt-4 text-xs text-muted-foreground">
                    Pending Charges
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ summary.pending_charges.toLocaleString() }}
                </p>
            </article>

            <article
                class="rounded-3xl border border-sidebar-border/70 bg-background px-5 py-5 shadow-sm"
            >
                <CheckCircle2 class="size-5 text-muted-foreground" />
                <p class="mt-4 text-xs text-muted-foreground">
                    Settled Charges
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ summary.settled_charges.toLocaleString() }}
                </p>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{
                        allocationSummary.active_charge_allocations.toLocaleString()
                    }}
                    active allocations
                </p>
            </article>
        </section>

        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-background shadow-sm"
        >
            <div class="border-b border-sidebar-border/70 px-6 py-5">
                <h2
                    class="text-lg font-semibold tracking-tight text-foreground"
                >
                    Charge List
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    Each row shows which member has the charge, how much it is,
                    and whether it was settled.
                </p>
            </div>

            <div v-if="charges.length > 0" class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                >
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Member</th>
                            <th class="px-4 py-3 font-medium">Charge</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Amount</th>
                            <th class="px-4 py-3 font-medium">Timeline</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="charge in charges"
                            :key="charge.id"
                            class="align-top"
                        >
                            <td class="px-4 py-4">
                                <p class="font-medium text-foreground">
                                    {{ charge.member_name || 'Unknown member' }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Charge #{{ charge.id }}
                                </p>
                            </td>
                            <td class="px-4 py-4">
                                <p class="font-medium text-foreground">
                                    {{ charge.charge_title || 'Charge item' }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ charge.charge_code || 'No code' }}
                                </p>
                            </td>
                            <td class="px-4 py-4">
                                <Badge :variant="statusVariant(charge.status)">
                                    {{ statusLabel(charge.status) }}
                                </Badge>
                                <p class="mt-2 text-xs text-muted-foreground">
                                    Allocated
                                    {{ money(charge.allocated_amount) }}
                                </p>
                            </td>
                            <td class="px-4 py-4 font-medium text-foreground">
                                {{ money(charge.amount) }}
                            </td>
                            <td class="px-4 py-4 text-muted-foreground">
                                <p>
                                    Effective:
                                    {{ charge.effective_at || 'Not recorded' }}
                                </p>
                                <p class="mt-1">
                                    Settled:
                                    {{
                                        charge.last_confirmed_at ||
                                        'Not settled'
                                    }}
                                </p>
                                <p
                                    v-if="charge.last_reversed_at"
                                    class="mt-1 text-rose-600"
                                >
                                    Reversed: {{ charge.last_reversed_at }}
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-else class="px-6 py-10 text-sm text-muted-foreground">
                No charges are available for your members yet.
            </div>
        </section>
    </div>
</template>
