<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type InvestmentLedger = {
    id: number;
    type: 'event' | 'business';
    title: string;
    status: string;
    is_closed: boolean;
    closed_at: string | null;
    lines: {
        code: string;
        label: string;
        kind: 'income' | 'expense';
        amount: number;
    }[];
    total_income: number;
    total_expense: number;
    result: number;
    cash: number;
    bkash: number;
    invested_capital: number;
    cycle_cash: number;
    close_blockers: string[];
    charges: {
        id: number;
        type: string;
        type_label: string;
        amount: number;
        note: string | null;
        charged_at: string | null;
        created_by_name: string | null;
    }[];
    charge_types: { value: string; label: string }[];
};

const props = defineProps<{
    ledger: InvestmentLedger;
    closeLabel: string;
}>();

const emit = defineEmits<{ close: [] }>();

const isChargeDialogOpen = ref(false);

const money = (amount: number): string =>
    `${amount.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 2 })} BDT`;

const incomeLines = computed(() =>
    props.ledger.lines.filter((line) => line.kind === 'income'),
);
const expenseLines = computed(() =>
    props.ledger.lines.filter((line) => line.kind === 'expense'),
);

const removeCharge = (chargeId: number) => {
    if (
        !window.confirm('Remove this charge? A reversal entry will be posted.')
    ) {
        return;
    }

    router.delete(`/admin/investments/${props.ledger.id}/charges/${chargeId}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">Total income</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(ledger.total_income) }}
                </p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">
                    Total expense (incl. charges)
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(ledger.total_expense) }}
                </p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">
                    {{ ledger.is_closed ? 'Final result' : 'Running result' }}
                </p>
                <p
                    class="mt-1 text-xl font-semibold tabular-nums"
                    :class="
                        ledger.result >= 0
                            ? 'text-emerald-600 dark:text-emerald-400'
                            : 'text-destructive'
                    "
                >
                    {{ money(ledger.result) }}
                </p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4">
                <p class="text-xs text-muted-foreground">Cycle bank money</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(ledger.cycle_cash) }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border/70 p-4 text-sm">
                <p class="text-xs text-muted-foreground">
                    Cash / float in hand
                </p>
                <p class="mt-1 font-semibold tabular-nums">
                    {{ money(ledger.cash) }}
                </p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4 text-sm">
                <p class="text-xs text-muted-foreground">bKash wallet</p>
                <p class="mt-1 font-semibold tabular-nums">
                    {{ money(ledger.bkash) }}
                </p>
            </div>
            <div class="rounded-xl border border-sidebar-border/70 p-4 text-sm">
                <p class="text-xs text-muted-foreground">
                    Capital still invested
                </p>
                <p class="mt-1 font-semibold tabular-nums">
                    {{ money(ledger.invested_capital) }}
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border border-sidebar-border/70">
                <p
                    class="border-b border-sidebar-border/70 px-4 py-2 text-sm font-medium"
                >
                    Income
                </p>
                <dl class="divide-y divide-sidebar-border/70 text-sm">
                    <div
                        v-for="line in incomeLines"
                        :key="line.code"
                        class="flex justify-between gap-2 px-4 py-2"
                    >
                        <dt class="text-muted-foreground">
                            {{ line.code }} · {{ line.label }}
                        </dt>
                        <dd class="tabular-nums">{{ money(line.amount) }}</dd>
                    </div>
                    <p
                        v-if="incomeLines.length === 0"
                        class="px-4 py-3 text-muted-foreground"
                    >
                        No income yet.
                    </p>
                </dl>
            </div>
            <div class="rounded-xl border border-sidebar-border/70">
                <p
                    class="border-b border-sidebar-border/70 px-4 py-2 text-sm font-medium"
                >
                    Expense
                </p>
                <dl class="divide-y divide-sidebar-border/70 text-sm">
                    <div
                        v-for="line in expenseLines"
                        :key="line.code"
                        class="flex justify-between gap-2 px-4 py-2"
                    >
                        <dt class="text-muted-foreground">
                            {{ line.code }} · {{ line.label }}
                        </dt>
                        <dd class="tabular-nums">{{ money(line.amount) }}</dd>
                    </div>
                    <p
                        v-if="expenseLines.length === 0"
                        class="px-4 py-3 text-muted-foreground"
                    >
                        No expense yet.
                    </p>
                </dl>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70">
            <div
                class="flex items-center justify-between gap-2 border-b border-sidebar-border/70 px-4 py-2"
            >
                <div>
                    <p class="text-sm font-medium">
                        Platform charges &amp; rent
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Expense of this project, income of the platform.
                    </p>
                </div>
                <Button
                    v-if="!ledger.is_closed"
                    size="sm"
                    variant="outline"
                    @click="isChargeDialogOpen = true"
                >
                    <Plus class="size-4" />
                    Add charge
                </Button>
            </div>
            <table class="min-w-full text-sm">
                <tbody class="divide-y divide-sidebar-border/70">
                    <tr v-for="charge in ledger.charges" :key="charge.id">
                        <td class="px-4 py-2">{{ charge.charged_at }}</td>
                        <td class="px-4 py-2">{{ charge.type_label }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ charge.note || '-' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ money(charge.amount) }}
                        </td>
                        <td class="w-10 px-2 py-2">
                            <Button
                                v-if="!ledger.is_closed"
                                size="icon"
                                variant="ghost"
                                @click="removeCharge(charge.id)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="ledger.charges.length === 0">
                        <td colspan="5" class="px-4 py-3 text-muted-foreground">
                            No charges.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div
            v-if="!ledger.is_closed"
            class="rounded-xl border p-4"
            :class="
                ledger.close_blockers.length
                    ? 'border-amber-500/40 bg-amber-500/5'
                    : 'border-emerald-500/40 bg-emerald-500/5'
            "
        >
            <div
                class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
            >
                <div class="space-y-1 text-sm">
                    <p class="font-medium">Close checklist</p>
                    <p
                        v-for="blocker in ledger.close_blockers"
                        :key="blocker"
                        class="flex items-start gap-2 text-amber-700 dark:text-amber-400"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" />
                        {{ blocker }}
                    </p>
                    <p
                        v-if="ledger.close_blockers.length === 0"
                        class="flex items-center gap-2 text-emerald-700 dark:text-emerald-400"
                    >
                        <CircleCheck class="size-4" />
                        Ready to close. The result moves to the cycle and the
                        project is locked.
                    </p>
                </div>
                <Button
                    :disabled="ledger.close_blockers.length > 0"
                    @click="emit('close')"
                >
                    {{ closeLabel }}
                </Button>
            </div>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            <Badge variant="secondary">Closed</Badge>
            <span class="ml-2">{{ ledger.closed_at }}</span>
        </p>

        <LedgerEntryDialog
            v-model:isOpen="isChargeDialogOpen"
            title="Add platform charge / rent"
            description="Posted as an expense of this project and income of the platform."
            :action="`/admin/investments/${ledger.id}/charges`"
            submit-label="Add charge"
            :fields="[
                {
                    name: 'type',
                    label: 'Type',
                    type: 'select',
                    options: ledger.charge_types,
                },
                { name: 'amount', label: 'Amount (BDT)', type: 'number' },
                { name: 'charged_at', label: 'Date', type: 'date' },
                { name: 'note', label: 'Note', type: 'text' },
            ]"
        />
    </div>
</template>
