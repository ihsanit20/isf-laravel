<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BarChart3, CalendarDays } from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type FundCycleItem = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    unit_amount: number;
    start_date: string | null;
    lock_date: string | null;
    maturity_date: string | null;
    settlement_date: string | null;
    allocations_count: number;
    total_allocated_amount: number;
    my_allocated_amount: number;
};

type EventItem = {
    id: number;
    title: string;
    total_paid_amount: number;
    other_income_amount: number;
    total_income_amount: number;
    total_expense_amount: number;
    net_profit_amount: number;
};

type Props = {
    fundCycle: FundCycleItem;
    events: EventItem[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Fund Cycles',
                href: '/fund-cycles',
            },
            {
                title: 'Cycle Details',
                href: '#',
            },
        ],
    },
});

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { maximumFractionDigits: 2 })} BDT`;

const eventTotals = computed(() => ({
    total_paid_amount: props.events.reduce(
        (sum, event) => sum + event.total_paid_amount,
        0,
    ),
    other_income_amount: props.events.reduce(
        (sum, event) => sum + event.other_income_amount,
        0,
    ),
    total_income_amount: props.events.reduce(
        (sum, event) => sum + event.total_income_amount,
        0,
    ),
    total_expense_amount: props.events.reduce(
        (sum, event) => sum + event.total_expense_amount,
        0,
    ),
    net_profit_amount: props.events.reduce(
        (sum, event) => sum + event.net_profit_amount,
        0,
    ),
}));

const cycleBars = computed(() => [
    {
        key: 'income',
        label: 'Total Income',
        value: eventTotals.value.total_income_amount,
        fillClass: 'bg-blue-600',
        trackClass: 'bg-blue-600/15',
    },
    {
        key: 'expense',
        label: 'Total Expense',
        value: eventTotals.value.total_expense_amount,
        fillClass: 'bg-amber-600',
        trackClass: 'bg-amber-600/15',
    },
    {
        key: 'profit',
        label: 'Net Profit',
        value: eventTotals.value.net_profit_amount,
        fillClass:
            eventTotals.value.net_profit_amount >= 0
                ? 'bg-emerald-600'
                : 'bg-destructive',
        trackClass:
            eventTotals.value.net_profit_amount >= 0
                ? 'bg-emerald-600/15'
                : 'bg-destructive/15',
    },
]);

const maxBarValue = computed(() =>
    Math.max(...cycleBars.value.map((bar) => Math.abs(bar.value)), 1),
);

const barWidthPercent = (value: number): number =>
    Math.min(100, (Math.abs(value) / maxBarValue.value) * 100);
</script>

<template>
    <Head :title="props.fundCycle.name" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-3xl border border-sidebar-border/70 bg-background p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-2xl">
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Cycle #{{ props.fundCycle.id }}
                    </p>
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight">
                        {{ props.fundCycle.name }}
                    </h1>
                    <div class="mt-3">
                        <Badge variant="outline">
                            {{ props.fundCycle.status_label }}
                        </Badge>
                    </div>
                </div>

                <Button as-child variant="outline" class="shrink-0">
                    <Link href="/fund-cycles">
                        <ArrowLeft class="size-4" />
                        Back to Fund Cycles
                    </Link>
                </Button>
            </div>

            <div class="mt-6 grid gap-3 text-sm md:grid-cols-3">
                <div class="rounded-2xl bg-background/75 px-3 py-3">
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <CalendarDays class="size-4" />
                        Timeline
                    </div>
                    <div class="mt-2 space-y-1 text-foreground">
                        <p>
                            Start:
                            {{ props.fundCycle.start_date || 'Not set' }}
                        </p>
                        <p>
                            Lock: {{ props.fundCycle.lock_date || 'Not set' }}
                        </p>
                        <p>
                            Maturity:
                            {{ props.fundCycle.maturity_date || 'Not set' }}
                        </p>
                        <p>
                            Settlement:
                            {{ props.fundCycle.settlement_date || 'Not set' }}
                        </p>
                    </div>
                </div>
                <div class="rounded-2xl bg-background/75 px-3 py-3">
                    <div class="text-xs text-muted-foreground">Allocation</div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Unit amount: {{ money(props.fundCycle.unit_amount) }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Total allocation:
                        {{ money(props.fundCycle.total_allocated_amount) }}
                    </p>
                    <p class="mt-1 font-medium text-foreground">
                        My allocation:
                        {{ money(props.fundCycle.my_allocated_amount) }}
                    </p>
                </div>
                <div class="rounded-2xl bg-background/75 px-3 py-3">
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <BarChart3 class="size-4" />
                        Income vs Expense
                    </div>
                    <div class="mt-3 space-y-2.5">
                        <div v-for="bar in cycleBars" :key="bar.key">
                            <div
                                class="flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span>{{ bar.label }}</span>
                                <span class="font-medium text-foreground">
                                    {{ money(bar.value) }}
                                </span>
                            </div>
                            <div
                                class="mt-1 h-2 w-full overflow-hidden rounded-full"
                                :class="bar.trackClass"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="bar.fillClass"
                                    :style="{
                                        width: `${barWidthPercent(bar.value)}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <h2 class="text-lg font-semibold tracking-tight">Events</h2>

            <div
                v-if="props.events.length > 0"
                class="mt-3 overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                    >
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">SL</th>
                                <th class="px-4 py-3 font-medium">Event</th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Paid Order
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Other Income
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Total Income
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Total Expense
                                </th>
                                <th class="px-4 py-3 text-right font-medium">
                                    Net Profit
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr
                                v-for="(event, index) in props.events"
                                :key="event.id"
                            >
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ index + 1 }}
                                </td>
                                <td class="px-4 py-3 font-medium">
                                    {{ event.title }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ money(event.total_paid_amount) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ money(event.other_income_amount) }}
                                </td>
                                <td class="px-4 py-3 text-right font-medium">
                                    {{ money(event.total_income_amount) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right text-muted-foreground"
                                >
                                    {{ money(event.total_expense_amount) }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-medium"
                                    :class="
                                        event.net_profit_amount >= 0
                                            ? 'text-emerald-600'
                                            : 'text-destructive'
                                    "
                                >
                                    {{ money(event.net_profit_amount) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-muted/40">
                            <tr>
                                <td class="px-4 py-3 font-semibold" colspan="2">
                                    Total
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ money(eventTotals.total_paid_amount) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ money(eventTotals.other_income_amount) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{ money(eventTotals.total_income_amount) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold">
                                    {{
                                        money(eventTotals.total_expense_amount)
                                    }}
                                </td>
                                <td
                                    class="px-4 py-3 text-right font-semibold"
                                    :class="
                                        eventTotals.net_profit_amount >= 0
                                            ? 'text-emerald-600'
                                            : 'text-destructive'
                                    "
                                >
                                    {{ money(eventTotals.net_profit_amount) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div
                v-else
                class="mt-3 rounded-[28px] border border-dashed border-sidebar-border/80 bg-background p-10 text-center shadow-sm"
            >
                <p class="text-sm text-muted-foreground">
                    No events have been published for this fund cycle yet.
                </p>
            </div>
        </section>
    </div>
</template>
