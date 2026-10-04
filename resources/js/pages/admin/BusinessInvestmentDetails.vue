<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import InvestmentLedgerPanel from '@/components/admin/InvestmentLedgerPanel.vue';
import type { InvestmentLedger } from '@/components/admin/InvestmentLedgerPanel.vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type BusinessTransactionItem = {
    id: number;
    type: string;
    type_label: string;
    amount: number;
    transaction_date: string | null;
    description: string | null;
    reference_no: string | null;
    created_by_name: string | null;
};

type Props = {
    investment: {
        id: number;
        title: string;
        counterparty: string | null;
        terms: string | null;
        invested_at: string | null;
        status: string;
        fund_cycle: { id: number; name: string | null };
    };
    ledger: InvestmentLedger;
    transactions: BusinessTransactionItem[];
    transactionTypes: { value: string; label: string }[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Business Investments', href: '/admin/businesses' },
            { title: 'Details', href: '#' },
        ],
    },
});

const props = defineProps<Props>();
const isTransactionDialogOpen = ref(false);
const isEditDialogOpen = ref(false);

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;

const removeTransaction = (transaction: BusinessTransactionItem) => {
    if (
        !confirm(
            `Remove "${transaction.type_label}"? A reversal entry will be posted.`,
        )
    ) {
        return;
    }

    router.delete(
        `/admin/businesses/${props.investment.id}/transactions/${transaction.id}`,
        { preserveScroll: true },
    );
};

const closeInvestment = () => {
    if (
        !confirm(
            'Close this business investment? Its result moves to the fund cycle and it will be locked.',
        )
    ) {
        return;
    }

    router.patch(`/admin/businesses/${props.investment.id}/close`, undefined, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${props.investment.title} - Business Investment`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-3xl space-y-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ props.investment.title }}
                    </h1>
                    <div
                        class="flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
                    >
                        <Badge
                            :variant="
                                props.investment.status === 'closed'
                                    ? 'secondary'
                                    : 'default'
                            "
                        >
                            {{ props.investment.status }}
                        </Badge>
                        <span>{{ props.investment.counterparty || '-' }}</span>
                        <span>·</span>
                        <Link
                            :href="`/admin/fund-cycles/${props.investment.fund_cycle.id}`"
                            class="text-primary underline underline-offset-4"
                        >
                            {{ props.investment.fund_cycle.name }}
                        </Link>
                    </div>
                    <p
                        v-if="props.investment.terms"
                        class="text-sm whitespace-pre-line text-muted-foreground"
                    >
                        {{ props.investment.terms }}
                    </p>
                </div>
                <Button
                    v-if="!props.ledger.is_closed"
                    variant="outline"
                    @click="isEditDialogOpen = true"
                >
                    Edit details
                </Button>
            </div>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 py-3"
            >
                <div>
                    <h2 class="text-base font-semibold">Transactions</h2>
                    <p class="text-xs text-muted-foreground">
                        Capital out, profit in, capital back, losses and other
                        income/expense — each one is a journal entry.
                    </p>
                </div>
                <Button
                    v-if="!props.ledger.is_closed"
                    size="sm"
                    @click="isTransactionDialogOpen = true"
                >
                    <Plus class="size-4" />
                    Add transaction
                </Button>
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr
                        v-for="transaction in props.transactions"
                        :key="transaction.id"
                    >
                        <td class="px-4 py-2">
                            {{ transaction.transaction_date }}
                        </td>
                        <td class="px-4 py-2">{{ transaction.type_label }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ transaction.description || '-' }}
                            <span v-if="transaction.reference_no">
                                · {{ transaction.reference_no }}
                            </span>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ money(transaction.amount) }}
                        </td>
                        <td class="w-10 px-2 py-2">
                            <Button
                                v-if="!props.ledger.is_closed"
                                size="icon"
                                variant="ghost"
                                @click="removeTransaction(transaction)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="props.transactions.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-6 text-center text-muted-foreground"
                        >
                            No transactions yet. Start with "Capital invested".
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <InvestmentLedgerPanel
                :ledger="props.ledger"
                close-label="Close investment"
                @close="closeInvestment"
            />
        </section>

        <LedgerEntryDialog
            v-model:isOpen="isTransactionDialogOpen"
            title="Add business transaction"
            description="Money moves between this business and the cycle's bank share."
            :action="`/admin/businesses/${props.investment.id}/transactions`"
            submit-label="Add"
            :fields="[
                {
                    name: 'type',
                    label: 'Type',
                    type: 'select',
                    options: props.transactionTypes,
                },
                { name: 'amount', label: 'Amount (BDT)', type: 'number' },
                { name: 'transaction_date', label: 'Date', type: 'date' },
                { name: 'reference_no', label: 'Reference', type: 'text' },
                { name: 'description', label: 'Description', type: 'textarea' },
            ]"
        />

        <LedgerEntryDialog
            v-model:isOpen="isEditDialogOpen"
            title="Edit business investment"
            :action="`/admin/businesses/${props.investment.id}`"
            method="put"
            :initial="{
                title: props.investment.title,
                counterparty: props.investment.counterparty,
                invested_at: props.investment.invested_at,
                terms: props.investment.terms,
            }"
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
    </div>
</template>
