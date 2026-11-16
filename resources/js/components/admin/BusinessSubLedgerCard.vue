<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Plus, SquarePen, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import LedgerEntryDialog from '@/components/admin/LedgerEntryDialog.vue';
import type { LedgerField } from '@/components/admin/LedgerEntryDialog.vue';
import StatCard from '@/components/shared/StatCard.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { amountToneClass, formatMoney, formatSignedMoney } from '@/lib/format';

export type BusinessSubEntryItem = {
    id: number;
    direction: 'expense' | 'income';
    category: string;
    amount: number;
    entry_date: string | null;
    note: string | null;
};

export type BusinessSubSummary = {
    total_expense: number;
    total_income: number;
    result: number;
    capital_given: number;
    unspent: number;
    by_category: {
        direction: 'expense' | 'income';
        category: string;
        total: number;
        count: number;
    }[];
};

type Props = {
    investmentId: number;
    entries: BusinessSubEntryItem[];
    summary: BusinessSubSummary;
    categories: string[];
};

const props = defineProps<Props>();

const directionLabel = (direction: string): string =>
    direction === 'income' ? 'আয়' : 'খরচ';

const directionClass = (direction: string): string =>
    direction === 'income'
        ? 'text-emerald-600 dark:text-emerald-400'
        : 'text-rose-600 dark:text-rose-400';

const isDialogOpen = ref(false);
const editing = ref<BusinessSubEntryItem | null>(null);

const baseUrl = computed(
    () => `/admin/businesses/${props.investmentId}/sub-entries`,
);

const fields = computed<LedgerField[]>(() => [
    {
        name: 'direction',
        label: 'ধরন',
        type: 'select',
        options: [
            { value: 'expense', label: 'খরচ' },
            { value: 'income', label: 'আয় / বিক্রি' },
        ],
    },
    {
        name: 'category',
        label: 'খাত',
        type: 'text',
        placeholder: 'যেমন: গরু কেনা, খাবার, ডাক্তার, বিক্রি',
        suggestions: props.categories,
    },
    { name: 'amount', label: 'পরিমাণ (BDT)', type: 'number' },
    { name: 'entry_date', label: 'তারিখ', type: 'date' },
    { name: 'note', label: 'নোট', type: 'textarea' },
]);

const openDialog = (entry: BusinessSubEntryItem | null) => {
    editing.value = entry;
    isDialogOpen.value = true;
};

const removeEntry = (entry: BusinessSubEntryItem) => {
    if (!confirm(`"${entry.category}" এন্ট্রিটি মুছে ফেলবেন?`)) {
        return;
    }

    router.delete(`${baseUrl.value}/${entry.id}`, { preserveScroll: true });
};
</script>

<template>
    <Card class="gap-4">
        <CardHeader>
            <div class="flex items-start justify-between gap-2">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <CardTitle>সাব হিসাব (নোট)</CardTitle>
                        <span
                            class="rounded-full border border-amber-300 bg-amber-50 px-2 py-0.5 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-300"
                        >
                            ISF হিসাবে প্রভাব নেই
                        </span>
                    </div>
                    <CardDescription class="mt-1">
                        অপারেটর টাকা কীভাবে খরচ করল আর কত বিক্রি করল তার নোট।
                        Journal-এ যায় না, যেকোনো সময় ঠিক করা যায়।
                    </CardDescription>
                </div>
                <Button size="sm" @click="openDialog(null)">
                    <Plus class="size-4" />
                    এন্ট্রি যোগ
                </Button>
            </div>
        </CardHeader>

        <CardContent class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="মোট খরচ"
                    :value="formatMoney(props.summary.total_expense)"
                />
                <StatCard
                    label="মোট বিক্রি / আয়"
                    :value="formatMoney(props.summary.total_income)"
                />
                <StatCard
                    label="অপারেটরের লাভ / ক্ষতি"
                    :value="formatSignedMoney(props.summary.result)"
                    :value-class="amountToneClass(props.summary.result)"
                />
                <StatCard
                    label="ISF মূলধন থেকে অব্যয়িত"
                    :value="formatSignedMoney(props.summary.unspent)"
                    :value-class="amountToneClass(props.summary.unspent)"
                    :hint="`ISF মূলধন: ${formatMoney(props.summary.capital_given)}`"
                />
            </div>

            <div
                v-if="props.summary.by_category.length > 0"
                class="rounded-xl bg-muted/50 px-4 py-3"
            >
                <p class="mb-2 text-sm font-medium">খাত অনুযায়ী</p>
                <dl class="grid gap-x-8 gap-y-1.5 text-sm sm:grid-cols-2">
                    <div
                        v-for="row in props.summary.by_category"
                        :key="`${row.direction}-${row.category}`"
                        class="flex justify-between gap-2"
                    >
                        <dt>
                            <span :class="directionClass(row.direction)">
                                {{ directionLabel(row.direction) }}
                            </span>
                            · {{ row.category }}
                            <span class="text-muted-foreground">
                                ({{ row.count }})
                            </span>
                        </dt>
                        <dd class="font-medium tabular-nums">
                            {{ formatMoney(row.total) }}
                        </dd>
                    </div>
                </dl>
            </div>
        </CardContent>

        <CardContent class="px-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12 pl-6">SL</TableHead>
                        <TableHead>তারিখ</TableHead>
                        <TableHead>ধরন</TableHead>
                        <TableHead>খাত</TableHead>
                        <TableHead>নোট</TableHead>
                        <TableHead class="text-right">পরিমাণ</TableHead>
                        <TableHead class="w-24 pr-6" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(entry, index) in props.entries"
                        :key="entry.id"
                    >
                        <TableCell
                            class="pl-6 text-muted-foreground tabular-nums"
                            >{{ index + 1 }}</TableCell
                        >
                        <TableCell>{{ entry.entry_date }}</TableCell>
                        <TableCell :class="directionClass(entry.direction)">
                            {{ directionLabel(entry.direction) }}
                        </TableCell>
                        <TableCell class="font-medium">
                            {{ entry.category }}
                        </TableCell>
                        <TableCell
                            class="whitespace-normal text-muted-foreground"
                        >
                            {{ entry.note || '—' }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ formatMoney(entry.amount) }}
                        </TableCell>
                        <TableCell class="pr-6">
                            <div class="flex justify-end gap-1">
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    @click="openDialog(entry)"
                                >
                                    <SquarePen class="size-4" />
                                </Button>
                                <Button
                                    size="icon"
                                    variant="ghost"
                                    @click="removeEntry(entry)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="props.entries.length === 0" :colspan="7">
                        এখনো কোনো এন্ট্রি নেই।
                    </TableEmpty>
                </TableBody>
            </Table>
        </CardContent>
    </Card>

    <LedgerEntryDialog
        v-model:isOpen="isDialogOpen"
        :title="
            editing ? 'সাব হিসাব এন্ট্রি সম্পাদনা' : 'সাব হিসাব এন্ট্রি যোগ'
        "
        description="শুধু নোট। ISF-এর হিসাব বা journal-এ কোনো প্রভাব পড়বে না।"
        :action="editing ? `${baseUrl}/${editing.id}` : baseUrl"
        :method="editing ? 'put' : 'post'"
        :initial="
            editing
                ? {
                      direction: editing.direction,
                      category: editing.category,
                      amount: editing.amount,
                      entry_date: editing.entry_date,
                      note: editing.note,
                  }
                : {}
        "
        :submit-label="editing ? 'সংরক্ষণ' : 'যোগ করুন'"
        :fields="fields"
    />
</template>
