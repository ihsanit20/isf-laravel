<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowDownToLine, Clock3, Landmark, Wallet } from 'lucide-vue-next';
import { ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
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
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatMoney } from '@/lib/format';

type StatementLine = {
    id: number;
    date: string | null;
    kind: string | null;
    description: string | null;
    member: string | null;
    credit: number;
    debit: number;
    balance: number;
};

type PayoutItem = {
    id: number;
    amount: number;
    payment_method_label: string;
    account_details: string | null;
    notes: string | null;
    status: 'pending' | 'paid' | 'rejected';
    reference_no: string | null;
    rejection_reason: string | null;
    requested_at: string | null;
    processed_at: string | null;
};

type Props = {
    availableBalance: number;
    investedCapital: number;
    investments: {
        cycle_id: number;
        cycle_name: string | null;
        status: string | null;
        amount: number;
    }[];
    lines: StatementLine[];
    payoutSummary: {
        pending_amount: number;
        requestable_amount: number;
    };
    paymentMethods: { value: string; label: string }[];
    payouts: PayoutItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Wallet', href: '/my-wallet' }],
    },
});

const props = defineProps<Props>();

const isRequestDialogOpen = ref(false);

const kindLabels: Record<string, string> = {
    deposit_verified: 'Deposit',
    fee_settled: 'Charge',
    cycle_allocated: 'Invested',
    cycle_settled: 'Returned',
    member_payout: 'Withdrawal',
};

const kindLabel = (kind: string | null): string =>
    (kind && kindLabels[kind]) || 'Adjustment';
</script>

<template>
    <Head title="Wallet" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Wallet"
            description="Your available balance, every movement in and out of it, and your withdrawal requests."
        >
            <template #actions>
                <Button
                    :disabled="props.payoutSummary.requestable_amount <= 0"
                    @click="isRequestDialogOpen = true"
                >
                    <ArrowDownToLine class="size-4" />
                    Withdraw
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 md:grid-cols-3">
            <StatCard
                label="Available balance"
                :value="formatMoney(props.availableBalance)"
                hint="Free to invest in a cycle or withdraw"
                :icon="Wallet"
            />
            <StatCard
                label="Pending withdrawals"
                :value="formatMoney(props.payoutSummary.pending_amount)"
                :hint="`You can request up to ${formatMoney(props.payoutSummary.requestable_amount)}`"
                :icon="Clock3"
            />
            <StatCard
                label="Invested in cycles"
                :value="formatMoney(props.investedCapital)"
                hint="Comes back to your balance when a cycle settles"
                :icon="Landmark"
            />
        </div>

        <Card v-if="props.investments.length > 0" class="gap-4 py-4">
            <CardHeader class="px-4">
                <CardTitle class="text-base">Money in running cycles</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2 px-4">
                <Link
                    v-for="investment in props.investments"
                    :key="investment.cycle_id"
                    :href="`/fund-cycles/${investment.cycle_id}`"
                    class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm transition-colors hover:bg-muted"
                >
                    <span class="font-medium">
                        {{ investment.cycle_name }}
                    </span>
                    <StatusBadge :status="investment.status" />
                    <span class="text-muted-foreground tabular-nums">
                        {{ formatMoney(investment.amount) }}
                    </span>
                </Link>
            </CardContent>
        </Card>

        <Tabs default-value="statement">
            <TabsList>
                <TabsTrigger value="statement">Statement</TabsTrigger>
                <TabsTrigger value="withdrawals" v-if="false">
                    Withdrawals
                    <span
                        v-if="props.payouts.length > 0"
                        class="text-xs text-muted-foreground"
                    >
                        {{ props.payouts.length }}
                    </span>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="statement">
                <Card class="py-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="w-12 pl-4">SL</TableHead>
                                <TableHead>Date</TableHead>
                                <TableHead>Type</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead class="text-right">In</TableHead>
                                <TableHead class="text-right">Out</TableHead>
                                <TableHead class="pr-4 text-right">
                                    Balance
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow
                                v-for="(line, index) in props.lines"
                                :key="line.id"
                            >
                                <TableCell
                                    class="pl-4 text-muted-foreground tabular-nums"
                                    >{{ index + 1 }}</TableCell
                                >
                                <TableCell>
                                    {{ line.date }}
                                </TableCell>
                                <TableCell>
                                    <span
                                        class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium"
                                    >
                                        {{ kindLabel(line.kind) }}
                                    </span>
                                </TableCell>
                                <TableCell class="whitespace-normal">
                                    {{ line.description }}
                                    <p
                                        v-if="line.member"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ line.member }}
                                    </p>
                                </TableCell>
                                <TableCell
                                    class="text-right text-emerald-600 tabular-nums dark:text-emerald-400"
                                >
                                    {{
                                        line.credit
                                            ? formatMoney(line.credit)
                                            : ''
                                    }}
                                </TableCell>
                                <TableCell class="text-right tabular-nums">
                                    {{
                                        line.debit
                                            ? formatMoney(line.debit)
                                            : ''
                                    }}
                                </TableCell>
                                <TableCell
                                    class="pr-4 text-right font-medium tabular-nums"
                                >
                                    {{ formatMoney(line.balance) }}
                                </TableCell>
                            </TableRow>
                            <TableEmpty
                                v-if="props.lines.length === 0"
                                :colspan="7"
                            >
                                No transactions yet. Your first verified deposit
                                will show up here.
                            </TableEmpty>
                        </TableBody>
                    </Table>
                </Card>
            </TabsContent>

            <TabsContent value="withdrawals">
                <EmptyState
                    v-if="props.payouts.length === 0"
                    :icon="ArrowDownToLine"
                    title="No withdrawal requests"
                    description="Request a withdrawal from your available balance. An admin pays it out and it shows here."
                />

                <div v-else class="grid gap-3">
                    <Card
                        v-for="payout in props.payouts"
                        :key="payout.id"
                        class="gap-3 py-4"
                    >
                        <CardHeader class="px-4">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <CardTitle class="text-lg tabular-nums">
                                    {{ formatMoney(payout.amount) }}
                                </CardTitle>
                                <StatusBadge :status="payout.status" />
                            </div>
                            <CardDescription>
                                {{ payout.payment_method_label }}
                                <template v-if="payout.account_details">
                                    · {{ payout.account_details }}
                                </template>
                            </CardDescription>
                        </CardHeader>
                        <CardContent
                            class="grid gap-1 px-4 text-sm text-muted-foreground"
                        >
                            <p>Requested {{ payout.requested_at }}</p>
                            <p v-if="payout.processed_at">
                                Processed {{ payout.processed_at }}
                                <template v-if="payout.reference_no">
                                    · Ref {{ payout.reference_no }}
                                </template>
                            </p>
                            <p v-if="payout.notes">{{ payout.notes }}</p>
                            <p
                                v-if="payout.rejection_reason"
                                class="text-rose-600 dark:text-rose-400"
                            >
                                {{ payout.rejection_reason }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </TabsContent>
        </Tabs>

        <LedgerEntryDialog
            v-model:isOpen="isRequestDialogOpen"
            title="Withdraw money"
            :description="`You can request up to ${formatMoney(props.payoutSummary.requestable_amount)}.`"
            action="/my-payouts"
            submit-label="Send request"
            :fields="[
                { name: 'amount', label: 'Amount (BDT)', type: 'number' },
                {
                    name: 'payment_method',
                    label: 'Receive via',
                    type: 'select',
                    options: props.paymentMethods,
                },
                {
                    name: 'account_details',
                    label: 'Account / number',
                    type: 'text',
                    placeholder: 'Bank account or mobile number',
                },
                { name: 'notes', label: 'Notes', type: 'textarea' },
            ]"
        />
    </div>
</template>
