<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CircleAlert, Plus, SquarePen, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InvestmentLedgerPanel from '@/components/admin/InvestmentLedgerPanel.vue';
import type { InvestmentLedger } from '@/components/admin/InvestmentLedgerPanel.vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
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
import { formatMoney } from '@/lib/format';

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

const isOpen = computed(
    () => !props.ledger.is_closed && !props.ledger.is_cancelled,
);

const page = usePage();

const actionErrors = computed(() => {
    const errors = page.props.errors as Record<string, string> | undefined;

    return ['close', 'cancel', 'ledger']
        .map((key) => errors?.[key])
        .filter((message): message is string => !!message);
});

const cancelInvestment = () => {
    if (
        !confirm(
            'Cancel this business investment? Nothing is posted and it is locked for good.',
        )
    ) {
        return;
    }

    router.patch(`/admin/businesses/${props.investment.id}/cancel`, undefined, {
        preserveScroll: true,
    });
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

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="props.investment.title">
            <template #actions>
                <Button
                    v-if="isOpen"
                    variant="outline"
                    @click="isEditDialogOpen = true"
                >
                    <SquarePen class="size-4" />
                    Edit details
                </Button>
            </template>
        </PageHeader>

        <div
            v-if="actionErrors.length > 0"
            class="flex gap-2 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-300"
        >
            <CircleAlert class="mt-0.5 size-4 shrink-0" />
            <div>
                <p v-for="message in actionErrors" :key="message">
                    {{ message }}
                </p>
            </div>
        </div>

        <div
            class="-mt-3 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
        >
            <StatusBadge :status="props.investment.status" />
            <span>{{ props.investment.counterparty || '—' }}</span>
            <span>·</span>
            <Link
                :href="`/admin/fund-cycles/${props.investment.fund_cycle.id}`"
                class="underline underline-offset-4 hover:text-foreground"
            >
                {{ props.investment.fund_cycle.name }}
            </Link>
            <span v-if="props.investment.invested_at">
                · since {{ props.investment.invested_at }}
            </span>
        </div>
        <p
            v-if="props.investment.terms"
            class="-mt-3 max-w-3xl text-sm whitespace-pre-line text-muted-foreground"
        >
            {{ props.investment.terms }}
        </p>

        <Card class="gap-4">
            <CardHeader>
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <CardTitle>Transactions</CardTitle>
                        <CardDescription class="mt-1">
                            Capital out, profit in, capital back, losses and
                            other income or expense. Each one is a journal
                            entry.
                        </CardDescription>
                    </div>
                    <Button
                        v-if="isOpen"
                        size="sm"
                        @click="isTransactionDialogOpen = true"
                    >
                        <Plus class="size-4" />
                        Add transaction
                    </Button>
                </div>
            </CardHeader>
            <CardContent class="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="pl-6">Date</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Description</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead class="w-12 pr-6" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="transaction in props.transactions"
                            :key="transaction.id"
                        >
                            <TableCell class="pl-6">
                                {{ transaction.transaction_date }}
                            </TableCell>
                            <TableCell class="font-medium">
                                {{ transaction.type_label }}
                            </TableCell>
                            <TableCell
                                class="whitespace-normal text-muted-foreground"
                            >
                                {{ transaction.description || '—' }}
                                <span v-if="transaction.reference_no">
                                    · {{ transaction.reference_no }}
                                </span>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(transaction.amount) }}
                            </TableCell>
                            <TableCell class="pr-6">
                                <Button
                                    v-if="isOpen"
                                    size="icon"
                                    variant="ghost"
                                    @click="removeTransaction(transaction)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </TableCell>
                        </TableRow>
                        <TableEmpty
                            v-if="props.transactions.length === 0"
                            :colspan="5"
                        >
                            No transactions yet. Start with "Capital invested".
                        </TableEmpty>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>

        <Card>
            <CardContent>
                <InvestmentLedgerPanel
                    :ledger="props.ledger"
                    close-label="Close investment"
                    @close="closeInvestment"
                    @cancel="cancelInvestment"
                />
            </CardContent>
        </Card>

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
