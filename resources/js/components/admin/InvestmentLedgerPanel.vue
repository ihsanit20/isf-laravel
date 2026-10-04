<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import StatCard from '@/components/shared/StatCard.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { amountToneClass, formatMoney, formatSignedMoney } from '@/lib/format';

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
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Total income"
                :value="formatMoney(ledger.total_income)"
            />
            <StatCard
                label="Total expense (incl. charges)"
                :value="formatMoney(ledger.total_expense)"
            />
            <StatCard
                :label="ledger.is_closed ? 'Final result' : 'Running result'"
                :value="formatSignedMoney(ledger.result)"
                :value-class="amountToneClass(ledger.result)"
            />
            <StatCard
                label="Cycle bank money"
                :value="formatMoney(ledger.cycle_cash)"
            />
        </div>

        <dl
            class="grid gap-x-8 gap-y-2 rounded-xl bg-muted/50 px-4 py-3 text-sm sm:grid-cols-3"
        >
            <div class="flex justify-between gap-2">
                <dt class="text-muted-foreground">Cash / float in hand</dt>
                <dd class="font-medium tabular-nums">
                    {{ formatMoney(ledger.cash) }}
                </dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-muted-foreground">bKash wallet</dt>
                <dd class="font-medium tabular-nums">
                    {{ formatMoney(ledger.bkash) }}
                </dd>
            </div>
            <div class="flex justify-between gap-2">
                <dt class="text-muted-foreground">Capital still invested</dt>
                <dd class="font-medium tabular-nums">
                    {{ formatMoney(ledger.invested_capital) }}
                </dd>
            </div>
        </dl>

        <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-xl border">
                <p class="border-b px-4 py-2 text-sm font-medium">Income</p>
                <dl class="divide-y divide-border text-sm">
                    <div
                        v-for="line in incomeLines"
                        :key="line.code"
                        class="flex justify-between gap-2 px-4 py-2"
                    >
                        <dt class="text-muted-foreground">
                            {{ line.code }} · {{ line.label }}
                        </dt>
                        <dd class="tabular-nums">
                            {{ formatMoney(line.amount) }}
                        </dd>
                    </div>
                    <p
                        v-if="incomeLines.length === 0"
                        class="px-4 py-3 text-muted-foreground"
                    >
                        No income yet.
                    </p>
                </dl>
            </div>
            <div class="rounded-xl border">
                <p class="border-b px-4 py-2 text-sm font-medium">Expense</p>
                <dl class="divide-y divide-border text-sm">
                    <div
                        v-for="line in expenseLines"
                        :key="line.code"
                        class="flex justify-between gap-2 px-4 py-2"
                    >
                        <dt class="text-muted-foreground">
                            {{ line.code }} · {{ line.label }}
                        </dt>
                        <dd class="tabular-nums">
                            {{ formatMoney(line.amount) }}
                        </dd>
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

        <div class="rounded-xl border">
            <div
                class="flex items-center justify-between gap-2 border-b px-4 py-2"
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
                <tbody class="divide-y divide-border">
                    <tr v-for="charge in ledger.charges" :key="charge.id">
                        <td class="px-4 py-2">{{ charge.charged_at }}</td>
                        <td class="px-4 py-2">{{ charge.type_label }}</td>
                        <td class="px-4 py-2 text-muted-foreground">
                            {{ charge.note || '-' }}
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums">
                            {{ formatMoney(charge.amount) }}
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
            <StatusBadge status="closed" />
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
