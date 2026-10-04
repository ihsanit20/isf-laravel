<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;
</script>

<template>
    <Head title="Business Investments" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Business Investments
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Fund cycle capital placed with an outside business. Each
                        one is a sub-business with its own income and expense.
                    </p>
                </div>
                <Button class="shrink-0" @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    New investment
                </Button>
            </div>
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
                            <th class="px-4 py-3 font-medium">Title</th>
                            <th class="px-4 py-3 font-medium">Fund cycle</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">
                                Capital invested
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Result
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="investment in props.investments"
                            :key="investment.id"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    :href="`/admin/businesses/${investment.id}`"
                                    class="font-medium text-primary underline underline-offset-4"
                                >
                                    {{ investment.title }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{ investment.counterparty || '-' }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ investment.fund_cycle.name }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    :variant="
                                        investment.status === 'closed'
                                            ? 'secondary'
                                            : 'default'
                                    "
                                >
                                    {{ investment.status }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ money(investment.invested_capital) }}
                            </td>
                            <td
                                class="px-4 py-3 text-right tabular-nums"
                                :class="
                                    investment.result < 0
                                        ? 'text-destructive'
                                        : ''
                                "
                            >
                                {{ money(investment.result) }}
                            </td>
                        </tr>
                        <tr v-if="props.investments.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No business investments yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

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
