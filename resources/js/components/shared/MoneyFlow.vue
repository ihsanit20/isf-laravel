<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next';
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

export type MoneyFlowStep = {
    label: string;
    amount: number;
    sign?: '+' | '−';
    highlight?: boolean;
};

defineProps<{
    steps: MoneyFlowStep[];
}>();
</script>

<template>
    <div
        class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:flex lg:flex-row lg:items-stretch"
    >
        <template v-for="(step, index) in steps" :key="step.label">
            <div
                :class="
                    cn(
                        'flex-1 rounded-xl border bg-card px-4 py-3 shadow-xs',
                        step.highlight && 'border-primary/40 bg-primary/5',
                    )
                "
            >
                <p class="text-xs text-muted-foreground">
                    <span v-if="step.sign" class="font-medium">
                        {{ step.sign }}
                    </span>
                    {{ step.label }}
                </p>
                <p
                    :class="
                        cn(
                            'mt-1 font-semibold tracking-tight tabular-nums',
                            step.highlight ? 'text-xl' : 'text-lg',
                        )
                    "
                >
                    {{ formatMoney(step.amount) }}
                </p>
            </div>
            <ChevronRight
                v-if="index < steps.length - 1"
                class="hidden size-4 shrink-0 self-center text-muted-foreground lg:block"
            />
        </template>
    </div>
</template>
