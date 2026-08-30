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

type IncomeExpenseTotals = {
    total_income_amount: number;
    total_expense_amount: number;
    net_profit_amount: number;
};

type ChartBar = {
    key: string;
    label: string;
    value: number;
    fillClass: string;
    trackClass: string;
};

const buildIncomeExpenseBars = (totals: IncomeExpenseTotals): ChartBar[] => [
    {
        key: 'income',
        label: 'Total Income',
        value: totals.total_income_amount,
        fillClass: 'bg-blue-600',
        trackClass: 'bg-blue-600/15',
    },
    {
        key: 'expense',
        label: 'Total Expense',
        value: totals.total_expense_amount,
        fillClass: 'bg-amber-600',
        trackClass: 'bg-amber-600/15',
    },
    {
        key: 'profit',
        label: 'Net Profit',
        value: totals.net_profit_amount,
        fillClass:
            totals.net_profit_amount >= 0 ? 'bg-emerald-600' : 'bg-destructive',
        trackClass:
            totals.net_profit_amount >= 0
                ? 'bg-emerald-600/15'
                : 'bg-destructive/15',
    },
];

const barWidthPercent = (value: number, max: number): number =>
    Math.min(100, (Math.abs(value) / Math.max(max, 1)) * 100);

const cycleBars = computed(() => buildIncomeExpenseBars(eventTotals.value));

const cycleBarMax = computed(() =>
    Math.max(...cycleBars.value.map((bar) => Math.abs(bar.value)), 1),
);

type AxisChartBar = ChartBar & {
    bottomPercent: number;
    heightPercent: number;
    labelSide: 'top' | 'bottom';
    labelOffsetPercent: number;
};

type EventChartGroup = {
    id: number;
    title: string;
    netMarginLabel: string;
    bars: AxisChartBar[];
};

// Rounds a rough tick step up to a "nice" 1 / 2 / 5 / 10 × 10^n value so axis
// labels land on clean numbers instead of arbitrary fractions.
const niceTickStep = (roughStep: number): number => {
    if (roughStep <= 0) {
        return 1;
    }

    const magnitude = 10 ** Math.floor(Math.log10(roughStep));
    const residual = roughStep / magnitude;
    const niceResidual =
        residual <= 1 ? 1 : residual <= 2 ? 2 : residual <= 5 ? 5 : 10;

    return niceResidual * magnitude;
};

const eventChartAxis = computed(() => {
    const values = props.events.flatMap((event) => [
        event.total_income_amount,
        event.total_expense_amount,
        event.net_profit_amount,
    ]);
    const rawMax = Math.max(...values, 0);
    const rawMin = Math.min(...values, 0);
    const step = niceTickStep(Math.max(rawMax, -rawMin, 1) / 4);
    const axisMax = Math.max(Math.ceil(rawMax / step) * step, step);
    const axisMin = rawMin < 0 ? Math.floor(rawMin / step) * step : 0;

    const ticks: number[] = [];

    for (let tick = axisMax; tick > axisMin; tick -= step) {
        ticks.push(Math.round(tick));
    }

    ticks.push(Math.round(axisMin));

    return { axisMax, axisMin, ticks };
});

const gridlinePercent = (tick: number): number => {
    const { axisMax, axisMin } = eventChartAxis.value;
    const span = Math.max(axisMax - axisMin, 1);

    return ((tick - axisMin) / span) * 100;
};

const eventChartGroups = computed<EventChartGroup[]>(() => {
    const { axisMax, axisMin } = eventChartAxis.value;
    const span = Math.max(axisMax - axisMin, 1);
    const zeroPercent = ((0 - axisMin) / span) * 100;

    return props.events.map((event) => {
        const bars = buildIncomeExpenseBars(event).map((bar) => {
            const heightPercent = (Math.abs(bar.value) / span) * 100;
            const bottomPercent =
                bar.value >= 0 ? zeroPercent : zeroPercent - heightPercent;
            // Value label sits at the bar's tip: above for a positive bar,
            // below for a negative one (which dips under the zero line).
            const labelSide: 'top' | 'bottom' =
                bar.value >= 0 ? 'bottom' : 'top';
            const labelOffsetPercent =
                labelSide === 'bottom'
                    ? bottomPercent + heightPercent
                    : 100 - bottomPercent;

            return {
                ...bar,
                bottomPercent,
                heightPercent,
                labelSide,
                labelOffsetPercent,
            };
        });

        const netMarginLabel =
            event.total_income_amount !== 0
                ? `${Math.round(
                      (event.net_profit_amount / event.total_income_amount) *
                          100,
                  )}%`
                : '—';

        return {
            id: event.id,
            title: event.title,
            netMarginLabel,
            bars,
        };
    });
});
</script>

<template>
    <Head :title="props.fundCycle.name" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm"
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
                </div>

                <Button as-child variant="outline" class="shrink-0">
                    <Link href="/fund-cycles">
                        <ArrowLeft class="size-4" />
                        Back to Fund Cycles
                    </Link>
                </Button>
            </div>

            <div class="mt-6 grid gap-3 text-sm md:grid-cols-3">
                <div class="rounded-2xl bg-background/75 px-3">
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
                <div class="rounded-2xl bg-background/75 px-3">
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
                <div class="rounded-2xl bg-background/75 px-3">
                    <div
                        class="flex items-center gap-2 text-xs text-muted-foreground"
                    >
                        <BarChart3 class="size-4" />
                        Income vs Expense
                    </div>
                    <div class="mt-3 space-y-1">
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
                                class="mt-1 h-1 w-full overflow-hidden rounded-full"
                                :class="bar.trackClass"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :class="bar.fillClass"
                                    :style="{
                                        width: `${barWidthPercent(bar.value, cycleBarMax)}%`,
                                    }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section v-if="props.events.length > 0">
            <h2 class="text-lg font-semibold tracking-tight">
                Event-wise Income &amp; Expense
            </h2>

            <div
                class="mt-3 rounded-xl border border-sidebar-border/70 bg-background p-5 shadow-sm dark:border-sidebar-border"
            >
                <div
                    class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground"
                >
                    <span class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-blue-600" />
                        Total Income
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-amber-600" />
                        Total Expense
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="size-2.5 rounded-sm bg-emerald-600" />
                        Net Profit
                        <span class="text-muted-foreground/70"
                            >(red = loss)</span
                        >
                    </span>
                </div>

                <div class="mt-6 flex gap-3">
                    <div
                        class="flex h-64 flex-col justify-between pb-px text-right text-xs text-muted-foreground"
                    >
                        <span v-for="tick in eventChartAxis.ticks" :key="tick">
                            {{ tick.toLocaleString() }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="relative h-64">
                            <div
                                v-for="tick in eventChartAxis.ticks"
                                :key="`grid-${tick}`"
                                class="absolute inset-x-0 border-t border-dashed border-sidebar-border/60"
                                :style="{
                                    bottom: `${gridlinePercent(tick)}%`,
                                }"
                            />

                            <div
                                class="relative flex h-full items-stretch justify-around gap-2"
                            >
                                <div
                                    v-for="group in eventChartGroups"
                                    :key="group.id"
                                    class="flex flex-1 items-end justify-center gap-1"
                                >
                                    <div
                                        v-for="bar in group.bars"
                                        :key="bar.key"
                                        class="relative h-full w-full max-w-5"
                                    >
                                        <span
                                            class="absolute left-1/2 -translate-x-1/2 text-[10px] font-medium text-nowrap text-foreground"
                                            :style="
                                                bar.labelSide === 'bottom'
                                                    ? {
                                                          bottom: `calc(${bar.labelOffsetPercent}% + 4px)`,
                                                      }
                                                    : {
                                                          top: `calc(${bar.labelOffsetPercent}% + 4px)`,
                                                      }
                                            "
                                        >
                                            {{ bar.value.toLocaleString() }}
                                        </span>
                                        <div
                                            class="absolute w-full rounded-t"
                                            :class="bar.fillClass"
                                            :style="{
                                                bottom: `${bar.bottomPercent}%`,
                                                height: `${bar.heightPercent}%`,
                                            }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-2 flex justify-around gap-2">
                            <div
                                v-for="group in eventChartGroups"
                                :key="`label-${group.id}`"
                                class="flex-1 truncate text-center text-xs text-muted-foreground"
                                :title="group.title"
                            >
                                {{ group.title }}
                            </div>
                        </div>

                        <div class="mt-1 flex justify-around gap-2">
                            <div
                                v-for="group in eventChartGroups"
                                :key="`margin-${group.id}`"
                                class="flex-1 text-center text-xs text-muted-foreground"
                            >
                                Net Margin: {{ group.netMarginLabel }}
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
