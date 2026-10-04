<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;

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

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Accounts
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Every figure here is read from the double-entry journal.
                        Member money and platform money are kept apart.
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link href="/admin/accounts/journal">Open journal</Link>
                </Button>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70">
                    <p
                        class="border-b border-sidebar-border/70 px-4 py-2 text-sm font-medium"
                    >
                        Where the money is
                    </p>
                    <dl class="divide-y divide-sidebar-border/70 text-sm">
                        <div
                            v-for="row in position"
                            :key="row.key"
                            class="flex justify-between px-4 py-2"
                        >
                            <dt class="text-muted-foreground">
                                {{ row.label }}
                            </dt>
                            <dd class="tabular-nums">
                                {{ money(props.treasury[row.key] ?? 0) }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div class="rounded-xl border border-sidebar-border/70">
                    <p
                        class="border-b border-sidebar-border/70 px-4 py-2 text-sm font-medium"
                    >
                        Whose money it is
                    </p>
                    <dl class="divide-y divide-sidebar-border/70 text-sm">
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
                                        ? 'text-destructive'
                                        : ''
                                "
                            >
                                {{ money(props.treasury[row.key] ?? 0) }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <h2 class="text-lg font-semibold">Platform income &amp; expense</h2>
            <p class="text-sm text-muted-foreground">
                Fees, platform charges and rent in; bank, SMS and office costs
                out. Not related to member balances.
            </p>
            <div class="mt-4 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <p class="text-xs text-muted-foreground">Income</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ money(props.platform.income) }}
                    </p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <p class="text-xs text-muted-foreground">Expense</p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ money(props.platform.expense) }}
                    </p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <p class="text-xs text-muted-foreground">Platform fund</p>
                    <p
                        class="mt-1 text-xl font-semibold tabular-nums"
                        :class="
                            props.platform.fund < 0 ? 'text-destructive' : ''
                        "
                    >
                        {{ money(props.platform.fund) }}
                    </p>
                </div>
            </div>
            <dl
                class="mt-4 divide-y divide-sidebar-border/70 rounded-xl border border-sidebar-border/70 text-sm"
            >
                <div
                    v-for="line in props.platform.lines"
                    :key="line.code"
                    class="flex justify-between px-4 py-2"
                >
                    <dt class="text-muted-foreground">
                        {{ line.code }} · {{ line.name }}
                        <Badge variant="outline" class="ml-2">{{
                            line.type
                        }}</Badge>
                    </dt>
                    <dd class="tabular-nums">{{ money(line.amount) }}</dd>
                </div>
                <p
                    v-if="props.platform.lines.length === 0"
                    class="px-4 py-3 text-muted-foreground"
                >
                    No platform entries yet.
                </p>
            </dl>
        </section>

        <section
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div class="px-6 pt-6">
                <h2 class="text-lg font-semibold">Fund cycles</h2>
            </div>
            <table
                class="mt-4 min-w-full divide-y divide-sidebar-border/70 text-sm"
            >
                <thead class="bg-muted/40 text-left">
                    <tr>
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
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr v-for="cycle in props.cycles" :key="cycle.id">
                        <td class="px-4 py-3">
                            <Link
                                :href="`/admin/fund-cycles/${cycle.id}`"
                                class="text-primary underline underline-offset-4"
                            >
                                {{ cycle.name }}
                            </Link>
                            <Badge variant="outline" class="ml-2">
                                {{
                                    cycle.is_settled ? 'settled' : cycle.status
                                }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ money(cycle.capital) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ money(cycle.cash) }}
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">
                            {{ money(cycle.deployed) }}
                        </td>
                        <td
                            class="px-4 py-3 text-right tabular-nums"
                            :class="cycle.result < 0 ? 'text-destructive' : ''"
                        >
                            {{ money(cycle.result) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>

        <section
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div class="px-6 pt-6">
                <h2 class="text-lg font-semibold">Trial balance</h2>
                <p class="text-sm text-muted-foreground">
                    Total debit must always equal total credit.
                </p>
            </div>
            <table
                class="mt-4 min-w-full divide-y divide-sidebar-border/70 text-sm"
            >
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Account</th>
                        <th class="px-4 py-3 font-medium">Type</th>
                        <th class="px-4 py-3 text-right font-medium">Debit</th>
                        <th class="px-4 py-3 text-right font-medium">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr v-for="row in props.trialBalance" :key="row.code">
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
                            {{ row.debit ? money(row.debit) : '' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ row.credit ? money(row.credit) : '' }}
                        </td>
                    </tr>
                    <tr class="font-semibold">
                        <td colspan="2" class="px-4 py-2">Total</td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ money(props.trialTotals.debit) }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ money(props.trialTotals.credit) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
