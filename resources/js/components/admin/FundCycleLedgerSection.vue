<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    amountToneClass,
    formatMoney,
    formatSignedMoney,
    titleCase,
} from '@/lib/format';

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
    <section class="space-y-6 rounded-xl border bg-card p-6 shadow-xs">
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
            <StatusBadge :status="ledger.is_settled ? 'settled' : 'running'" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Member capital"
                :value="formatMoney(ledger.capital)"
            />
            <StatCard
                label="Cycle money in bank"
                :value="formatMoney(ledger.cash)"
            />
            <StatCard
                label="Deployed (cash, bKash, business)"
                :value="formatMoney(ledger.deployed)"
            />
            <StatCard
                label="Cycle result"
                :value="formatSignedMoney(ledger.result)"
                :value-class="amountToneClass(ledger.result)"
            />
        </div>

        <div class="rounded-xl border">
            <div
                class="flex items-center justify-between gap-2 border-b px-4 py-2"
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
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="(investment, index) in ledger.investments"
                        :key="investment.id"
                    >
                        <td
                            class="w-12 px-4 py-2 text-muted-foreground tabular-nums"
                        >
                            {{ index + 1 }}
                        </td>
                        <td class="px-4 py-2 text-xs text-muted-foreground">
                            {{ titleCase(investment.type) }}
                        </td>
                        <td class="px-4 py-2">
                            <Link
                                v-if="investment.url"
                                :href="investment.url"
                                class="font-medium hover:underline"
                            >
                                {{ investment.title }}
                            </Link>
                            <span v-else>{{ investment.title }}</span>
                        </td>
                        <td class="px-4 py-2">
                            <StatusBadge :status="investment.status" />
                        </td>
                        <td
                            class="px-4 py-2 text-right tabular-nums"
                            :class="amountToneClass(investment.result)"
                        >
                            {{ formatSignedMoney(investment.result) }}
                        </td>
                    </tr>
                    <tr>
                        <td colspan="4" class="px-4 py-2 text-muted-foreground">
                            Cycle-level income − expense
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{
                                formatSignedMoney(
                                    ledger.cycle_income - ledger.cycle_expense,
                                )
                            }}
                        </td>
                    </tr>
                    <tr class="bg-muted/50 font-medium">
                        <td colspan="4" class="px-4 py-2">Total</td>
                        <td
                            class="px-4 py-2 text-right tabular-nums"
                            :class="amountToneClass(ledger.result)"
                        >
                            {{ formatSignedMoney(ledger.result) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border">
            <div
                class="flex items-center justify-between gap-2 border-b px-4 py-2"
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
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="(transaction, index) in transactions"
                        :key="transaction.id"
                    >
                        <td
                            class="w-12 px-4 py-2 text-muted-foreground tabular-nums"
                        >
                            {{ index + 1 }}
                        </td>
                        <td class="px-4 py-2">
                            {{ transaction.transaction_date }}
                        </td>
                        <td class="px-4 py-2">
                            <span
                                class="text-xs font-medium"
                                :class="
                                    transaction.direction === 'income'
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-rose-600 dark:text-rose-400'
                                "
                            >
                                {{ titleCase(transaction.direction) }}
                            </span>
                        </td>
                        <td class="px-4 py-2">
                            {{ transaction.category_label }}
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ transaction.description || '-' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ formatMoney(transaction.amount) }}
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
                        <td colspan="7" class="px-4 py-3 text-muted-foreground">
                            No cycle-level entries.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border">
            <div class="border-b px-4 py-2">
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
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="w-12 px-4 py-2 font-medium">SL</th>
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
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="(member, index) in ledger.members"
                        :key="member.member_id"
                    >
                        <td
                            class="w-12 px-4 py-2 text-muted-foreground tabular-nums"
                        >
                            {{ index + 1 }}
                        </td>
                        <td class="px-4 py-2">{{ member.name }}</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ formatMoney(member.capital) }}
                        </td>
                        <td
                            class="px-4 py-2 text-right tabular-nums"
                            :class="amountToneClass(member.share)"
                        >
                            {{ formatSignedMoney(member.share) }}
                        </td>
                        <td
                            class="px-4 py-2 text-right font-medium tabular-nums"
                        >
                            {{ formatMoney(member.payout) }}
                        </td>
                    </tr>
                    <tr v-if="ledger.members.length === 0">
                        <td colspan="5" class="px-4 py-3 text-muted-foreground">
                            No member capital in this cycle.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div
                v-if="!ledger.is_settled"
                class="space-y-2 border-t p-4 text-sm"
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
