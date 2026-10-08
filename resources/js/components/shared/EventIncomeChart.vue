<script setup lang="ts">
import { computed } from 'vue';
import { formatMoney, formatSignedMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

type ChartEvent = {
    id: number;
    title: string;
    total_income_amount: number;
    total_expense_amount: number;
    net_profit_amount: number;
};

const props = defineProps<{ events: ChartEvent[] }>();

const series = [
    {
        key: 'income',
        label: 'Total income',
        swatch: 'bg-blue-600 dark:bg-blue-500',
    },
    {
        key: 'expense',
        label: 'Total expense',
        swatch: 'bg-orange-600',
    },
    {
        key: 'net',
        label: 'Net profit',
        swatch: 'bg-emerald-600',
    },
] as const;

const compactNumber = new Intl.NumberFormat(undefined, {
    maximumFractionDigits: 0,
});

function niceStep(rough: number): number {
    const power = 10 ** Math.floor(Math.log10(rough));
    const fraction = rough / power;

    if (fraction <= 1) {
        return power;
    }

    if (fraction <= 2) {
        return 2 * power;
    }

    if (fraction <= 5) {
        return 5 * power;
    }

    return 10 * power;
}

const scale = computed(() => {
    const values = props.events.flatMap((event) => [
        event.total_income_amount,
        event.total_expense_amount,
        event.net_profit_amount,
    ]);
    const high = Math.max(0, ...values);
    const low = Math.min(0, ...values);
    const step = niceStep((high - low || 1) / 3);
    const max = Math.ceil(high / step) * step || step;
    const min = Math.floor(low / step) * step;
    const ticks: number[] = [];

    for (let tick = min; tick <= max; tick += step) {
        ticks.push(tick);
    }

    return { min, max, ticks };
});

function position(value: number): number {
    const { min, max } = scale.value;

    return ((value - min) / (max - min)) * 100;
}

const zero = computed(() => position(0));

const showValueLabels = computed(() => props.events.length <= 8);

const groups = computed(() =>
    props.events.map((event) => {
        const values = {
            income: event.total_income_amount,
            expense: event.total_expense_amount,
            net: event.net_profit_amount,
        };

        return {
            id: event.id,
            title: event.title,
            margin:
                event.total_income_amount > 0
                    ? Math.round(
                          (event.net_profit_amount /
                              event.total_income_amount) *
                              100,
                      )
                    : null,
            bars: series.map((item) => {
                const value = values[item.key];
                const end = position(value);
                const isLoss = item.key === 'net' && value < 0;

                return {
                    ...item,
                    value,
                    negative: value < 0,
                    bottom: Math.min(end, zero.value),
                    height: Math.abs(end - zero.value),
                    swatch: isLoss ? 'bg-rose-600' : item.swatch,
                };
            }),
        };
    }),
);
</script>

<template>
    <div class="flex flex-col gap-4">
        <div
            class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground"
        >
            <span
                v-for="item in series"
                :key="item.key"
                class="flex items-center gap-1.5"
            >
                <span :class="cn('size-2.5 rounded-full', item.swatch)" />
                {{ item.label }}
            </span>
            <span>(red = loss)</span>
        </div>

        <div class="flex gap-2">
            <div
                class="relative my-4 h-60 w-14 shrink-0 text-xs text-muted-foreground"
            >
                <span
                    v-for="tick in scale.ticks"
                    :key="tick"
                    class="absolute right-0 translate-y-1/2 tabular-nums"
                    :style="{ bottom: `${position(tick)}%` }"
                >
                    {{ compactNumber.format(tick) }}
                </span>
            </div>

            <div class="min-w-0 flex-1 overflow-x-auto">
                <div
                    class="flex flex-col"
                    :style="{ minWidth: `${props.events.length * 6}rem` }"
                >
                    <div class="relative my-4 h-60">
                        <div
                            v-for="tick in scale.ticks"
                            :key="tick"
                            :class="
                                cn(
                                    'absolute inset-x-0 border-t',
                                    tick === 0
                                        ? 'border-border'
                                        : 'border-dashed border-border/60',
                                )
                            "
                            :style="{ bottom: `${position(tick)}%` }"
                        />

                        <div class="absolute inset-0 flex">
                            <div
                                v-for="group in groups"
                                :key="group.id"
                                class="group relative flex flex-1 justify-center gap-0.5 hover:bg-muted/40"
                            >
                                <div
                                    v-for="bar in group.bars"
                                    :key="bar.key"
                                    class="relative h-full w-5 sm:w-6"
                                >
                                    <div
                                        :class="
                                            cn(
                                                'absolute inset-x-0',
                                                bar.swatch,
                                                bar.negative
                                                    ? 'rounded-b'
                                                    : 'rounded-t',
                                            )
                                        "
                                        :style="{
                                            bottom: `${bar.bottom}%`,
                                            height: `${bar.height}%`,
                                        }"
                                    />
                                    <span
                                        v-if="showValueLabels"
                                        class="absolute left-1/2 -translate-x-1/2 text-[10px] whitespace-nowrap text-foreground tabular-nums"
                                        :class="
                                            bar.negative
                                                ? 'translate-y-full pt-0.5'
                                                : 'pb-0.5'
                                        "
                                        :style="
                                            bar.negative
                                                ? {
                                                      bottom: `${bar.bottom}%`,
                                                  }
                                                : {
                                                      bottom: `${bar.bottom + bar.height}%`,
                                                  }
                                        "
                                    >
                                        {{ compactNumber.format(bar.value) }}
                                    </span>
                                </div>

                                <div
                                    class="pointer-events-none absolute top-2 left-1/2 z-10 hidden w-48 -translate-x-1/2 rounded-md border bg-popover p-2 text-xs text-popover-foreground shadow-md group-hover:block"
                                >
                                    <p class="mb-1 font-medium">
                                        {{ group.title }}
                                    </p>
                                    <p
                                        v-for="bar in group.bars"
                                        :key="bar.key"
                                        class="flex items-center justify-between gap-2"
                                    >
                                        <span
                                            class="flex items-center gap-1.5 text-muted-foreground"
                                        >
                                            <span
                                                :class="
                                                    cn(
                                                        'size-2 rounded-full',
                                                        bar.swatch,
                                                    )
                                                "
                                            />
                                            {{ bar.label }}
                                        </span>
                                        <span class="tabular-nums">
                                            {{
                                                bar.key === 'net'
                                                    ? formatSignedMoney(
                                                          bar.value,
                                                      )
                                                    : formatMoney(bar.value)
                                            }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex pt-2">
                        <div
                            v-for="group in groups"
                            :key="group.id"
                            class="flex-1 px-1 text-center text-xs"
                        >
                            <p class="line-clamp-2">{{ group.title }}</p>
                            <p
                                v-if="group.margin !== null"
                                class="mt-0.5 text-muted-foreground"
                            >
                                Net margin: {{ group.margin }}%
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
