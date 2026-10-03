<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type CycleLedger = {
    capital: number;
    cash: number;
    deployed: number;
    investments_result: number;
    cycle_income: number;
    cycle_expense: number;
    result: number;
    is_settled: boolean;
    blockers: string[];
    investments: {
        id: number;
        type: 'event' | 'business';
        title: string;
        status: string;
        result: number;
        url: string | null;
    }[];
    members: {
        member_id: number;
        user_id: number;
        name: string;
        capital: number;
        share: number;
        payout: number;
    }[];
};

export type CycleTransaction = {
    id: number;
    direction: 'income' | 'expense';
    category: string;
    category_label: string;
    amount: number;
    transaction_date: string | null;
    description: string | null;
    created_by_name: string | null;
};

const props = defineProps<{
    cycleId: number;
    ledger: CycleLedger;
    transactions: CycleTransaction[];
    transactionCategories: { value: string; label: string }[];
}>();

const isTransactionDialogOpen = ref(false);
const isBusinessDialogOpen = ref(false);

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;

const removeTransaction = (transaction: CycleTransaction) => {
    if (!confirm('Remove this cycle entry? A reversal entry will be posted.')) {
        return;
    }

    router.delete(
        `/admin/fund-cycles/${props.cycleId}/transactions/${transaction.id}`,
        { preserveScroll: true },
    );
};

const settle = () => {
    if (
        !confirm(
            'Settle this fund cycle? Capital and result will be returned to member balances, and the cycle will be locked. This cannot be undone.',
        )
    ) {
        return;
    }

    router.post(`/admin/fund-cycles/${props.cycleId}/settle`, undefined, {
        preserveScroll: true,
    });
};
</script>

<template>
    <section
        class="space-y-6 rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
    >
        <div
            class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
        >
            <div>
                <h2 class="text-lg font-semibold">Cycle accounts (mudaraba)</h2>
                <p class="text-sm text-muted-foreground">
                    From the journal. The platform takes no share of the cycle
                    result.
                </p>
            </div>
            <Badge :variant="ledger.is_settled ? 'default' : 'secondary'">
                {{ ledger.is_settled ? 'Settled' : 'Open' }}
            </Badge>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">Member capital</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(ledger.capital) }}
                </p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">Cycle money in bank</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(ledger.cash) }}
                </p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">
                    Deployed (cash, bKash, business)
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(ledger.deployed) }}
                </p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">Cycle result</p>
                <p
                    class="mt-1 text-xl font-semibold tabular-nums"
                    :class="
                        ledger.result >= 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-destructive'
                    "
                >
                    {{ money(ledger.result) }}
                </p>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70">
            <div
                class="flex items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 py-2"
            >
                <p class="text-sm font-medium">Sub-businesses</p>
                <Button
                    v-if="!ledger.is_settled"
                    size="sm"
                    variant="outline"
                    @click="isBusinessDialogOpen = true"
                >
                    <Plus class="size-4" />
                    New business investment
                </Button>
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr
                        v-for="investment in ledger.investments"
                        :key="investment.id"
                    >
                        <td class="px-4 py-2">
                            <Badge variant="outline">{{
                                investment.type
                            }}</Badge>
                        </td>
                        <td class="px-4 py-2">
                            <Link
                                v-if="investment.url"
                                :href="investment.url"
                                class="text-primary underline underline-offset-4"
                            >
                                {{ investment.title }}
                            </Link>
                            <span v-else>{{ investment.title }}</span>
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ investment.status }}
                        </td>
                        <td
                            class="px-4 py-2 text-right tabular-nums"
                            :class="
                                investment.result < 0 ? 'text-destructive' : ''
                            "
                        >
                            {{ money(investment.result) }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="3" class="px-4 py-2 text-muted-foreground">
                            Cycle-level income − expense
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{
                                money(
                                    ledger.cycle_income - ledger.cycle_expense,
                                )
                            }}
                        </td>
                    </tr>
                    <tr class="font-medium">
                        <td colspan="3" class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ money(ledger.result) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-sidebar-border/70">
            <div
                class="flex items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 py-2"
            >
                <div>
                    <p class="text-sm font-medium">
                        Cycle-level income &amp; expense
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Not tied to any event or business (e.g. agreement
                        paperwork).
                    </p>
                </div>
                <Button
                    v-if="!ledger.is_settled"
                    size="sm"
                    variant="outline"
                    @click="isTransactionDialogOpen = true"
                >
                    <Plus class="size-4" />
                    Add entry
                </Button>
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr
                        v-for="transaction in transactions"
                        :key="transaction.id"
                    >
                        <td class="px-4 py-2">
                            {{ transaction.transaction_date }}
                        </td>
                        <td class="px-4 py-2">
                            <Badge
                                :variant="
                                    transaction.direction === 'income'
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{ transaction.direction }}
                            </Badge>
                        </td>
                        <td class="px-4 py-2">
                            {{ transaction.category_label }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ transaction.description || '-' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ money(transaction.amount) }}
                        </td>
                        <td class="w-10 px-2 py-2">
                            <Button
                                v-if="!ledger.is_settled"
                                size="icon"
                                variant="ghost"
                                @click="removeTransaction(transaction)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="transactions.length === 0">
                        <td colspan="6" class="px-4 py-3 text-muted-foreground">
                            No cycle-level entries.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-sidebar-border/70">
            <div class="border-b border-sidebar-border/70 px-4 py-2">
                <p class="text-sm font-medium">
                    {{
                        ledger.is_settled ? 'Settlement' : 'Settlement preview'
                    }}
                </p>
                <p class="text-xs text-muted-foreground">
                    Capital + share of the result, split by capital ratio, goes
                    to each member's available balance.
                </p>
            </div>
            <table class="min-w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-4 py-2 font-medium">Member</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Capital
                        </th>
                        <th class="px-4 py-2 text-right font-medium">Share</th>
                        <th class="px-4 py-2 text-right font-medium">
                            Returns
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr
                        v-for="member in ledger.members"
                        :key="member.member_id"
                    >
                        <td class="px-4 py-2">{{ member.name }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ money(member.capital) }}
                        </td>
                        <td
                            class="px-4 py-2 text-right tabular-nums"
                            :class="member.share < 0 ? 'text-destructive' : ''"
                        >
                            {{ money(member.share) }}
                        </td>
                        <td
                            class="px-4 py-2 text-right font-medium tabular-nums"
                        >
                            {{ money(member.payout) }}
                        </td>
                    </tr>
                    <tr v-if="ledger.members.length === 0">
                        <td colspan="4" class="px-4 py-3 text-muted-foreground">
                            No member capital in this cycle.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-if="!ledger.is_settled"
                class="space-y-2 border-t border-sidebar-border/70 p-4 text-sm"
            >
                <p
                    v-for="blocker in ledger.blockers"
                    :key="blocker"
                    class="flex items-start gap-2 text-amber-700 dark:text-amber-400"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" />
                    {{ blocker }}
                </p>
                <p
                    v-if="ledger.blockers.length === 0"
                    class="flex items-center gap-2 text-emerald-700 dark:text-emerald-400"
                >
                    <CircleCheck class="size-4" />
                    Everything is closed and reconciled. Ready to settle.
                </p>
                <Button :disabled="ledger.blockers.length > 0" @click="settle">
                    Settle cycle
                </Button>
            </div>
        </div>

        <LedgerEntryDialog
            v-model:isOpen="isTransactionDialogOpen"
            title="Add cycle-level entry"
            description="Paid from / received into this cycle's share of the bank."
            :action="`/admin/fund-cycles/${cycleId}/transactions`"
            submit-label="Add entry"
            :fields="[
                {
                    name: 'direction',
                    label: 'Type',
                    type: 'select',
                    options: [
                        { value: 'expense', label: 'Expense' },
                        { value: 'income', label: 'Income' },
                    ],
                },
                {
                    name: 'category',
                    label: 'Category',
                    type: 'select',
                    options: transactionCategories,
                },
                { name: 'amount', label: 'Amount (BDT)', type: 'number' },
                { name: 'transaction_date', label: 'Date', type: 'date' },
                { name: 'description', label: 'Description', type: 'textarea' },
            ]"
        />

        <LedgerEntryDialog
            v-model:isOpen="isBusinessDialogOpen"
            title="New business investment"
            description="A sub-business of this cycle. Add capital and results from its page."
            action="/admin/businesses"
            submit-label="Create"
            :extra="{ fund_cycle_id: cycleId }"
            :fields="[
                { name: 'title', label: 'Title', type: 'text' },
                {
                    name: 'counterparty',
                    label: 'Business / partner',
                    type: 'text',
                },
                { name: 'invested_at', label: 'Start date', type: 'date' },
                {
                    name: 'terms',
                    label: 'Profit-sharing terms',
                    type: 'textarea',
                },
            ]"
        />
    </section>
</template>
