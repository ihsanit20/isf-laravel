<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { amountToneClass, formatMoney, formatSignedMoney } from '@/lib/format';

type BusinessInvestmentItem = {
    id: number;
    title: string;
    counterparty: string | null;
    status: string;
    invested_at: string | null;
    fund_cycle: { id: number; name: string | null };
    invested_capital: number;
    result: number;
};

type Props = {
    investments: BusinessInvestmentItem[];
    fundCycles: { value: string; label: string }[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Business Investments', href: '/admin/businesses' },
        ],
    },
});

const props = defineProps<Props>();
const isCreateDialogOpen = ref(false);
</script>

<template>
    <Head title="Business Investments" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Business investments"
            description="Fund cycle capital placed with an outside business. Each one has its own income and expenses and closes into the cycle result."
        >
            <template #actions>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    New investment
                </Button>
            </template>
        </PageHeader>

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Business</TableHead>
                        <TableHead>Fund cycle</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right"
                            >Capital invested</TableHead
                        >
                        <TableHead class="pr-4 text-right">Result</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="investment in props.investments"
                        :key="investment.id"
                    >
                        <TableCell class="pl-4">
                            <Link
                                :href="`/admin/businesses/${investment.id}`"
                                class="font-medium hover:underline"
                            >
                                {{ investment.title }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ investment.counterparty || '—' }}
                            </p>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ investment.fund_cycle.name }}
                        </TableCell>
                        <TableCell>
                            <StatusBadge :status="investment.status" />
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ formatMoney(investment.invested_capital) }}
                        </TableCell>
                        <TableCell
                            class="pr-4 text-right font-medium tabular-nums"
                            :class="amountToneClass(investment.result)"
                        >
                            {{ formatSignedMoney(investment.result) }}
                        </TableCell>
                    </TableRow>
                    <TableEmpty
                        v-if="props.investments.length === 0"
                        :colspan="5"
                    >
                        No business investments yet.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

        <LedgerEntryDialog
            v-model:isOpen="isCreateDialogOpen"
            title="New business investment"
            description="Add capital, profit and returns from its page after creating."
            action="/admin/businesses"
            submit-label="Create"
            :fields="[
                {
                    name: 'fund_cycle_id',
                    label: 'Fund cycle',
                    type: 'select',
                    options: props.fundCycles,
                },
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
