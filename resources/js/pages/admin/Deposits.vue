<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Check, FileBadge2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import DepositReviewDialog from '@/components/admin/DepositReviewDialog.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney, titleCase } from '@/lib/format';

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

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedDeposits = {
    data: AdminDeposit[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

type FilterOptions = {
    statuses: string[];
    payment_methods: Array<{
        value: string;
        label: string;
    }>;
};

type ActiveFilters = {
    status: string;
    payment_method: string;
    search: string;
    from_date: string;
    to_date: string;
    per_page: number;
};

type Summary = {
    bank_balance: number;
    bkash_balance: number;
    event_cash: number;
    business_investment: number;
    members_available: number;
    cycle_capital: number;
    cycle_results: number;
    platform_fund: number;
    platform_income: number;
    platform_expense: number;
    fee_income: number;
    charge_income: number;
    verified_amount: number;
    rejected_amount: number;
    pending_amount: number;
    pending_count: number;
};

type Props = {
    deposits: PaginatedDeposits;
    summary: Summary;
    filters: ActiveFilters;
    filterOptions: FilterOptions;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Deposits',
                href: '/admin/deposits',
            },
        ],
    },
});

const props = defineProps<Props>();

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

const openVerifyDialog = (deposit: AdminDeposit) => {
    selectedDeposit.value = deposit;
    isVerifyDialogOpen.value = true;
};

const openRejectDialog = (deposit: AdminDeposit) => {
    selectedDeposit.value = deposit;
    isRejectDialogOpen.value = true;
};

const decodePaginationLabel = (label: string): string => {
    return label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace('&hellip;', '…');
};
</script>

<template>
    <Head title="Deposits" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Deposits"
            description="Check each proof against the bank and verify it. A verified deposit becomes the user’s available balance."
        />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Waiting for review"
                :value="formatMoney(props.summary.pending_amount)"
                :hint="`${props.summary.pending_count} pending · rejected ${formatMoney(props.summary.rejected_amount)}`"
                :value-class="
                    props.summary.pending_count > 0
                        ? 'text-amber-600 dark:text-amber-400'
                        : ''
                "
            />
            <StatCard
                label="Verified deposits"
                :value="formatMoney(props.summary.verified_amount)"
            />
            <StatCard
                label="Joint bank (journal)"
                :value="formatMoney(props.summary.bank_balance)"
                :hint="`+ bKash ${formatMoney(props.summary.bkash_balance)} · event cash ${formatMoney(props.summary.event_cash)} · in businesses ${formatMoney(props.summary.business_investment)}`"
            />
            <Link
                href="/admin/accounts"
                class="rounded-xl transition-colors focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
            >
                <StatCard
                    class="h-full hover:border-foreground/20"
                    label="Platform fund"
                    :value="formatMoney(props.summary.platform_fund)"
                    :hint="`+ fees ${formatMoney(props.summary.fee_income)} · + charges & rent ${formatMoney(props.summary.charge_income)} · − expense ${formatMoney(props.summary.platform_expense)}`"
                />
            </Link>
        </div>

        <Card class="gap-0 py-4">
            <CardContent class="px-4">
                <dl class="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-3">
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">
                            Members’ available balances
                        </dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatMoney(props.summary.members_available) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">Capital in cycles</dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatMoney(props.summary.cycle_capital) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">
                            Closed project results
                        </dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatMoney(props.summary.cycle_results) }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <form
            method="get"
            action="/admin/deposits"
            class="grid gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7"
        >
            <Input
                name="search"
                :default-value="props.filters.search"
                placeholder="Search user, email, reference"
            />
            <select
                name="status"
                :value="props.filters.status"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
            >
                <option value="">All statuses</option>
                <option
                    v-for="status in props.filterOptions.statuses"
                    :key="status"
                    :value="status"
                >
                    {{ titleCase(status) }}
                </option>
            </select>
            <select
                name="payment_method"
                :value="props.filters.payment_method"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
            >
                <option value="">All payment methods</option>
                <option
                    v-for="method in props.filterOptions.payment_methods"
                    :key="method.value"
                    :value="method.value"
                >
                    {{ method.label }}
                </option>
            </select>
            <Input
                name="from_date"
                type="date"
                :default-value="props.filters.from_date"
            />
            <Input
                name="to_date"
                type="date"
                :default-value="props.filters.to_date"
            />
            <select
                name="per_page"
                :value="props.filters.per_page"
                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs dark:bg-input/30"
            >
                <option
                    v-for="size in [15, 25, 50, 100, 200, 500]"
                    :key="size"
                    :value="size"
                >
                    {{ size }} per page
                </option>
            </select>
            <div class="flex gap-2">
                <Button type="submit">Filter</Button>
                <Button type="button" variant="outline" as-child>
                    <Link href="/admin/deposits">Reset</Link>
                </Button>
            </div>
        </form>

        <Card class="gap-0 py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12 pl-4">SL</TableHead>
                        <TableHead>User</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead>Method</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Reviewed</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(deposit, index) in props.deposits.data"
                        :key="deposit.id"
                    >
                        <TableCell
                            class="pl-4 text-muted-foreground tabular-nums"
                            >{{ (props.deposits.from ?? 1) + index }}</TableCell
                        >
                        <TableCell>
                            <p class="font-medium">
                                {{ deposit.user.name || 'Unknown account' }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ deposit.user.email || '—' }}
                            </p>
                        </TableCell>
                        <TableCell class="text-right">
                            <p class="font-medium tabular-nums">
                                {{ formatMoney(deposit.amount) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ deposit.deposit_date }}
                            </p>
                        </TableCell>
                        <TableCell class="max-w-xs whitespace-normal">
                            <p>{{ deposit.payment_method_label }}</p>
                            <p class="text-xs text-muted-foreground">
                                <template v-if="deposit.reference_no">
                                    Ref {{ deposit.reference_no }}
                                </template>
                                <template v-if="deposit.notes">
                                    · {{ deposit.notes }}
                                </template>
                            </p>
                            <a
                                v-if="deposit.proof_url"
                                :href="deposit.proof_url"
                                class="inline-flex items-center gap-1 text-xs font-medium underline underline-offset-4"
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileBadge2 class="size-3" />
                                View proof
                            </a>
                        </TableCell>
                        <TableCell class="max-w-xs whitespace-normal">
                            <StatusBadge :status="deposit.status" />
                            <p
                                v-if="deposit.rejection_reason"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ deposit.rejection_reason }}
                            </p>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            <p>{{ deposit.verifier || '—' }}</p>
                            <p class="text-xs">{{ deposit.verified_at }}</p>
                        </TableCell>
                        <TableCell class="pr-4">
                            <div
                                v-if="deposit.status === 'pending'"
                                class="flex justify-end gap-2"
                            >
                                <Button
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
                        </TableCell>
                    </TableRow>
                    <TableEmpty
                        v-if="props.deposits.data.length === 0"
                        :colspan="7"
                    >
                        No deposits found.
                    </TableEmpty>
                </TableBody>
            </Table>

            <div
                class="flex flex-col gap-3 border-t px-4 py-3 text-sm md:flex-row md:items-center md:justify-between"
            >
                <p class="text-muted-foreground">
                    Showing {{ props.deposits.from || 0 }} to
                    {{ props.deposits.to || 0 }} of
                    {{ props.deposits.total.toLocaleString() }} deposits
                </p>
                <div class="flex flex-wrap items-center gap-1">
                    <Link
                        v-for="link in props.deposits.links"
                        :key="link.label"
                        :href="link.url || ''"
                        :class="[
                            'rounded-md border px-3 py-1.5 text-xs',
                            link.active
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted',
                            !link.url ? 'pointer-events-none opacity-50' : '',
                        ]"
                    >
                        {{ decodePaginationLabel(link.label) }}
                    </Link>
                </div>
            </div>
        </Card>

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
