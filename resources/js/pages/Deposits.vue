<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    BadgeCheck,
    Clock3,
    FileText,
    Plus,
    Wallet,
    WalletCards,
} from 'lucide-vue-next';
import { computed } from 'vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney } from '@/lib/format';

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
    total_rejected_deposit_count: number;
    total_charge_allocated_amount: number;
    total_fund_cycle_allocated_amount: number;
    total_allocated_amount: number;
    total_cycle_returned_amount: number;
    total_payout_amount: number;
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
        breadcrumbs: [{ title: 'Deposits', href: '/my-deposits' }],
    },
});

const props = defineProps<Props>();

const pendingDeposits = computed(() =>
    props.deposits.filter((deposit) => deposit.status === 'pending'),
);

const pendingAmount = computed(() =>
    pendingDeposits.value.reduce((sum, deposit) => sum + deposit.amount, 0),
);
</script>

<template>
    <Head title="Deposits" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Deposits"
            description="Send money to the ISF account and submit the proof here. Once an admin verifies it, the amount is added to your balance."
        >
            <template #actions>
                <Button as-child>
                    <Link href="/my-deposits/create">
                        <Plus class="size-4" />
                        New deposit
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-3">
            <StatCard
                label="Verified deposits"
                :value="formatMoney(props.summary.total_verified_amount)"
                :hint="`${props.summary.total_deposit_count} deposits submitted`"
                :icon="BadgeCheck"
            />
            <StatCard
                label="Waiting for verification"
                :value="formatMoney(pendingAmount)"
                :hint="`${pendingDeposits.length} pending`"
                :icon="Clock3"
            />
            <StatCard
                label="Available balance"
                :value="formatMoney(props.summary.total_allocatable_amount)"
                hint="Ready for charges and investments"
                :icon="Wallet"
            />
        </div>

        <EmptyState
            v-if="props.deposits.length === 0"
            :icon="WalletCards"
            title="No deposits yet"
            description="Your first deposit starts the journey: after verification you can pay member charges and invest in fund cycles."
        >
            <Button as-child>
                <Link href="/my-deposits/create">Submit a deposit</Link>
            </Button>
        </EmptyState>

        <Card v-else class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12 pl-4">SL</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="pr-4">Details</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(deposit, index) in props.deposits"
                        :key="deposit.id"
                    >
                        <TableCell
                            class="pl-4 text-muted-foreground tabular-nums"
                            >{{ index + 1 }}</TableCell
                        >
                        <TableCell>
                            {{ deposit.deposit_date }}
                        </TableCell>
                        <TableCell class="text-right font-medium tabular-nums">
                            {{ formatMoney(deposit.amount) }}
                        </TableCell>
                        <TableCell>
                            {{ deposit.payment_method_label }}
                            <p
                                v-if="deposit.reference_no"
                                class="text-xs text-muted-foreground"
                            >
                                Ref {{ deposit.reference_no }}
                            </p>
                        </TableCell>
                        <TableCell>
                            <StatusBadge :status="deposit.status" />
                        </TableCell>
                        <TableCell
                            class="max-w-xs pr-4 text-sm whitespace-normal"
                        >
                            <p
                                v-if="deposit.rejection_reason"
                                class="text-rose-600 dark:text-rose-400"
                            >
                                {{ deposit.rejection_reason }}
                            </p>
                            <p
                                v-else-if="deposit.verified_at"
                                class="text-muted-foreground"
                            >
                                Verified {{ deposit.verified_at }}
                            </p>
                            <p
                                v-if="deposit.notes"
                                class="text-muted-foreground"
                            >
                                {{ deposit.notes }}
                            </p>
                            <a
                                v-if="deposit.proof_url"
                                :href="deposit.proof_url"
                                target="_blank"
                                rel="noopener"
                                class="inline-flex items-center gap-1 text-xs underline underline-offset-4"
                            >
                                <FileText class="size-3" />
                                Proof
                            </a>
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </Card>

        <Card v-if="props.chargeAllocations.length > 0" class="gap-4">
            <CardHeader>
                <CardTitle>Charges paid from balance</CardTitle>
                <CardDescription>
                    Member charges are paid from the Members page.
                </CardDescription>
            </CardHeader>
            <CardContent class="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12 pl-6">SL</TableHead>
                            <TableHead>Member</TableHead>
                            <TableHead>Charge</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead class="pr-6">Paid</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(
                                allocation, index
                            ) in props.chargeAllocations"
                            :key="allocation.id"
                        >
                            <TableCell
                                class="pl-6 text-muted-foreground tabular-nums"
                                >{{ index + 1 }}</TableCell
                            >
                            <TableCell>
                                {{ allocation.member_name }}
                            </TableCell>
                            <TableCell>{{ allocation.charge_title }}</TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(allocation.amount) }}
                            </TableCell>
                            <TableCell class="pr-6 text-muted-foreground">
                                {{ allocation.confirmed_at }}
                                <StatusBadge
                                    v-if="allocation.reversed_at"
                                    status="cancelled"
                                    label="Reversed"
                                    class="ml-1"
                                />
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
