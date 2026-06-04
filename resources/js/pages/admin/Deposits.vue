<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
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
    total_deposit_amount: number;
    verified_amount: number;
    rejected_amount: number;
    total_general_expense: number;
    total_event_bank_withdrawals: number;
    total_charge_settlements: number;
    current_balance: number;
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
                title: 'Deposit Reviews',
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

const decodePaginationLabel = (label: string): string => {
    return label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace('&hellip;', '…');
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

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">
                    Total Verified Amount
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ money(props.summary.verified_amount) }}
                </p>
                <div class="mt-3 flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">
                        Rejected: {{ money(props.summary.rejected_amount) }}
                    </span>
                </div>
                <div class="mt-1 flex items-center justify-between text-sm">
                    <span class="text-muted-foreground">
                        Total:
                        {{ money(props.summary.total_deposit_amount) }}
                    </span>
                </div>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">Current Balance</p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Cash in joint bank account (wallet)
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ money(props.summary.current_balance) }}
                </p>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{ props.summary.pending_count.toLocaleString() }} pending
                    · {{ money(props.summary.pending_amount) }}
                </p>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <p class="text-xs font-medium text-foreground">
                    Balance breakdown
                </p>
                <p class="mt-0.5 text-xs text-muted-foreground">
                    Verified deposits minus outflows
                </p>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">Verified deposits</dt>
                        <dd class="font-medium tabular-nums text-foreground">
                            +{{ money(props.summary.verified_amount) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">General expenses</dt>
                        <dd class="font-medium tabular-nums text-foreground">
                            −{{ money(props.summary.total_general_expense) }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">
                            <Link
                                href="/admin/events"
                                class="text-primary underline underline-offset-4"
                            >
                                Event bank withdrawals
                            </Link>
                        </dt>
                        <dd class="font-medium tabular-nums text-foreground">
                            −{{
                                money(
                                    props.summary.total_event_bank_withdrawals,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-2">
                        <dt class="text-muted-foreground">
                            Charge settlements
                        </dt>
                        <dd class="font-medium tabular-nums text-foreground">
                            −{{
                                money(props.summary.total_charge_settlements)
                            }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">
                    Total General Expense
                </p>
                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ money(props.summary.total_general_expense) }}
                </p>
            </div>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
        >
            <form
                method="get"
                action="/admin/deposits"
                class="grid gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-7"
            >
                <input
                    name="search"
                    type="text"
                    :value="props.filters.search"
                    placeholder="Search user, email, reference"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                />
                <select
                    name="status"
                    :value="props.filters.status"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option value="">All status</option>
                    <option
                        v-for="status in props.filterOptions.statuses"
                        :key="status"
                        :value="status"
                    >
                        {{ statusLabel(status as DepositStatus) }}
                    </option>
                </select>
                <select
                    name="payment_method"
                    :value="props.filters.payment_method"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
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
                <input
                    name="from_date"
                    type="date"
                    :value="props.filters.from_date"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                />
                <input
                    name="to_date"
                    type="date"
                    :value="props.filters.to_date"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                />
                <select
                    name="per_page"
                    :value="props.filters.per_page"
                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                >
                    <option :value="15">15 per page</option>
                    <option :value="25">25 per page</option>
                    <option :value="50">50 per page</option>
                    <option :value="100">100 per page</option>
                    <option :value="200">200 per page</option>
                    <option :value="500">500 per page</option>
                </select>
                <div class="flex gap-2">
                    <Button type="submit" size="sm" class="h-9">Filter</Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-9"
                        as-child
                    >
                        <Link href="/admin/deposits">Reset</Link>
                    </Button>
                </div>
            </form>
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
                        <tr
                            v-for="deposit in props.deposits.data"
                            :key="deposit.id"
                        >
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
                        <tr v-if="props.deposits.data.length === 0">
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

            <div
                class="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 text-sm md:flex-row md:items-center md:justify-between"
            >
                <p class="text-muted-foreground">
                    Showing {{ props.deposits.from || 0 }} to
                    {{ props.deposits.to || 0 }} of
                    {{ props.deposits.total.toLocaleString() }} deposits
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-for="link in props.deposits.links"
                        :key="link.label"
                        :href="link.url || ''"
                        :class="[
                            'rounded-md border px-3 py-1.5 text-xs',
                            link.active
                                ? 'border-foreground bg-foreground text-background'
                                : 'border-sidebar-border/70 text-muted-foreground',
                            !link.url ? 'pointer-events-none opacity-50' : '',
                        ]"
                    >
                        {{ decodePaginationLabel(link.label) }}
                    </Link>
                </div>
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
