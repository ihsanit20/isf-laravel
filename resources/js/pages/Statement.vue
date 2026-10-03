<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

type Props = {
    availableBalance: number;
    investedCapital: number;
    investments: {
        cycle_id: number;
        cycle_name: string | null;
        status: string | null;
        amount: number;
    }[];
    lines: {
        id: number;
        date: string | null;
        kind: string | null;
        description: string | null;
        member: string | null;
        credit: number;
        debit: number;
        balance: number;
    }[];
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My Statement', href: '/my-statement' }],
    },
});

const props = defineProps<Props>();

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;
</script>

<template>
    <Head title="My Statement" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        My Statement
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Every movement of your balance: deposits, fees, cycle
                        allocations, returned capital and profit, payouts.
                    </p>
                </div>
                <Button variant="outline" as-child>
                    <Link href="/my-payouts">Request payout</Link>
                </Button>
            </div>

            <div class="mt-6 grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <p class="text-xs text-muted-foreground">
                        Available balance
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ money(props.availableBalance) }}
                    </p>
                </div>
                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <p class="text-xs text-muted-foreground">
                        Invested in running cycles
                    </p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums">
                        {{ money(props.investedCapital) }}
                    </p>
                    <ul class="mt-2 space-y-1 text-sm text-muted-foreground">
                        <li
                            v-for="investment in props.investments"
                            :key="investment.cycle_id"
                        >
                            <Link
                                :href="`/fund-cycles/${investment.cycle_id}`"
                                class="underline underline-offset-4"
                            >
                                {{ investment.cycle_name }}
                            </Link>
                            · {{ money(investment.amount) }}
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section
            class="overflow-x-auto rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <table class="min-w-full divide-y divide-sidebar-border/70 text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-4 py-3 font-medium">Date</th>
                        <th class="px-4 py-3 font-medium">Description</th>
                        <th class="px-4 py-3 text-right font-medium">In</th>
                        <th class="px-4 py-3 text-right font-medium">Out</th>
                        <th class="px-4 py-3 text-right font-medium">
                            Balance
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr v-for="line in props.lines" :key="line.id">
                        <td class="px-4 py-2 whitespace-nowrap">
                            {{ line.date }}
                        </td>
                        <td class="px-4 py-2">
                            {{ line.description }}
                            <p
                                v-if="line.member"
                                class="text-xs text-muted-foreground"
                            >
                                {{ line.member }}
                            </p>
                        </td>
                        <td
                            class="px-4 py-2 text-right text-emerald-700 tabular-nums dark:text-emerald-400"
                        >
                            {{ line.credit ? money(line.credit) : '' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ line.debit ? money(line.debit) : '' }}
                        </td>
                        <td
                            class="px-4 py-2 text-right font-medium tabular-nums"
                        >
                            {{ money(line.balance) }}
                        </td>
                    </tr>
                    <tr v-if="props.lines.length === 0">
                        <td
                            colspan="5"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            No transactions yet.
                        </td>
                    </tr>
                </tbody>
            </table>
        </section>
    </div>
</template>
