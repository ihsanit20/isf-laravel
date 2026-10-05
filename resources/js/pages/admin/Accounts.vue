<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { amountToneClass, formatMoney, formatSignedMoney } from '@/lib/format';

type Props = {
    treasury: Record<string, number>;
    trialBalance: {
        code: string;
        name: string;
        type: string;
        scope: string | null;
        debit: number;
        credit: number;
    }[];
    trialTotals: { debit: number; credit: number };
    platform: {
        lines: { code: string; name: string; type: string; amount: number }[];
        income: number;
        expense: number;
        fund: number;
    };
    cycles: {
        id: number;
        name: string;
        status: string;
        is_settled: boolean;
        capital: number;
        cash: number;
        deployed: number;
        result: number;
    }[];
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Accounts', href: '/admin/accounts' }],
    },
});

const props = defineProps<Props>();

const position = [
    { key: 'bank_balance', label: 'Joint bank (journal)' },
    { key: 'bkash_balance', label: 'bKash wallet' },
    { key: 'event_cash', label: 'Event cash / float' },
    { key: 'business_investment', label: 'Capital in businesses' },
];

const owners = [
    { key: 'members_available', label: 'Members — available balance' },
    { key: 'cycle_capital', label: 'Members — capital in cycles' },
    { key: 'cycle_results', label: 'Members — closed project results' },
    { key: 'platform_fund', label: 'Platform fund' },
];
</script>

<template>
    <Head title="Accounts" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Accounts"
            description="Every figure here is read from the double-entry journal. Member money and platform money are kept apart."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link href="/admin/accounts/journal">Open journal</Link>
                </Button>
            </template>
        </PageHeader>

        <section>
            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border bg-card shadow-xs">
                    <p class="border-b px-4 py-3 text-sm font-medium">
                        Where the money is
                    </p>
                    <dl class="divide-y divide-border text-sm">
                        <div
                            v-for="row in position"
                            :key="row.key"
                            class="flex justify-between px-4 py-2"
                        >
                            <dt class="text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd class="tabular-nums">
                                {{ formatMoney(props.treasury[row.key] ?? 0) }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div class="rounded-xl border bg-card shadow-xs">
                    <p class="border-b px-4 py-3 text-sm font-medium">
                        Whose money it is
                    </p>
                    <dl class="divide-y divide-border text-sm">
                        <div
                            v-for="row in owners"
                            :key="row.key"
                            class="flex justify-between px-4 py-2"
                        >
                            <dt class="text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd
                                class="tabular-nums"
                                :class="
                                    (props.treasury[row.key] ?? 0) < 0
                                        ? 'text-rose-600 dark:text-rose-400'
                                        : ''
                                "
                            >
                                {{ formatMoney(props.treasury[row.key] ?? 0) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section class="rounded-xl border bg-card p-6 shadow-xs">
            <h2 class="text-lg font-semibold">Platform income &amp; expense</h2>
            <p class="text-sm text-muted-foreground">
                Fees, platform charges and rent in; bank, SMS and office costs
                out. Not related to member balances.
            </p>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <StatCard
                    label="Income"
                    :value="formatMoney(props.platform.income)"
                />
                <StatCard
                    label="Expense"
                    :value="formatMoney(props.platform.expense)"
                />
                <StatCard
                    label="Platform fund"
                    :value="formatMoney(props.platform.fund)"
                    :value-class="
                        props.platform.fund < 0
                            ? 'text-rose-600 dark:text-rose-400'
                            : ''
                    "
                />
            </div>
            <dl class="mt-4 divide-y divide-border rounded-xl border text-sm">
                <div
                    v-for="line in props.platform.lines"
                    :key="line.code"
                    class="flex justify-between px-4 py-2"
                >
                    <dt class="text-muted-foreground">
                        {{ line.code }} · {{ line.name }}
                        <span
                            class="ml-2 rounded-md bg-muted px-1.5 py-0.5 text-xs"
                        >
                            {{ line.type }}
                        </span>
                    </dt>
                    <dd class="tabular-nums">{{ formatMoney(line.amount) }}</dd>
                </div>
                <p
                    v-if="props.platform.lines.length === 0"
                    class="px-4 py-3 text-muted-foreground"
                >
                    No platform entries yet.
                </p>
            </dl>
        </section>

        <section class="overflow-x-auto rounded-xl border bg-card shadow-xs">
            <div class="px-6 pt-6">
                <h2 class="text-lg font-semibold">Fund cycles</h2>
            </div>
            <table class="mt-4 min-w-full divide-y divide-border text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="w-12 px-4 py-3 font-medium">SL</th>
                        <th class="px-4 py-3 font-medium">Cycle</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Capital
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            In bank
                        </th>
                        <th class="px-4 py-3 text-right font-medium">
                            Deployed
                        </th>
                        <th class="px-4 py-3 text-right font-medium">Result</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr v-for="(cycle, index) in props.cycles" :key="cycle.id">
                        <td
                            class="w-12 px-4 py-3 text-muted-foreground tabular-nums"
                        >
                            {{ index + 1 }}
                        </td>
                        <td class="px-4 py-3">
                            <Link
                                :href="`/admin/fund-cycles/${cycle.id}`"
                                class="font-medium hover:underline"
                            >
                                {{ cycle.name }}
                            </Link>
                            <StatusBadge
                                class="ml-2"
                                :status="
                                    cycle.is_settled ? 'settled' : cycle.status
                                "
                            />
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ formatMoney(cycle.capital) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ formatMoney(cycle.cash) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ formatMoney(cycle.deployed) }}
                        </td>
                        <td
                            class="px-4 py-3 text-right tabular-nums"
                            :class="amountToneClass(cycle.result)"
                        >
                            {{ formatSignedMoney(cycle.result) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section class="overflow-x-auto rounded-xl border bg-card shadow-xs">
            <div class="px-6 pt-6">
                <h2 class="text-lg font-semibold">Trial balance</h2>
                <p class="text-sm text-muted-foreground">
                    Total debit must always equal total credit.
                </p>
            </div>
            <table class="mt-4 min-w-full divide-y divide-border text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="w-12 px-4 py-3 font-medium">SL</th>
                        <th class="px-4 py-3 font-medium">Account</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 text-right font-medium">Debit</th>
                        <th class="px-4 py-3 text-right font-medium">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="(row, index) in props.trialBalance"
                        :key="row.code"
                    >
                        <td
                            class="w-12 px-4 py-2 text-muted-foreground tabular-nums"
                        >
                            {{ index + 1 }}
                        </td>
                        <td class="px-4 py-2">
                            <Link
                                :href="`/admin/accounts/journal?account=${row.code}`"
                                class="hover:underline"
                            >
                                {{ row.code }} · {{ row.name }}
                            </Link>
                        </td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ row.type
                            }}<span v-if="row.scope"> · {{ row.scope }}</span>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ row.debit ? formatMoney(row.debit) : '' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ row.credit ? formatMoney(row.credit) : '' }}
                        </td>
                    </tr>
                    <tr class="bg-muted/50 font-semibold">
                        <td colspan="3" class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ formatMoney(props.trialTotals.debit) }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ formatMoney(props.trialTotals.credit) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
