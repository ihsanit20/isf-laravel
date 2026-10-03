<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Download,
    ExternalLink,
    Filter,
    RotateCcw,
    Search,
    X,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Option = { value: string; label: string };

type Dimension = {
    type: 'user' | 'member' | 'cycle' | 'investment' | 'order';
    id: number;
    label: string;
};

type JournalLineItem = {
    id: number;
    code: string | null;
    account: string | null;
    debit: number;
    credit: number;
    memo: string | null;
    matches: boolean;
    dimensions: Dimension[];
};

type JournalEntryItem = {
    id: number;
    entry_date: string | null;
    kind: string;
    description: string;
    idempotency_key: string;
    reversal_of_id: number | null;
    reversed_by_id: number | null;
    posted_by: string | null;
    posted_at: string | null;
    total: number;
    source: { label: string; url: string | null } | null;
    lines: JournalLineItem[];
};

type Filters = {
    search: string;
    kind: string;
    source: string;
    account: string;
    account_type: string;
    scope: string;
    cycle: number | null;
    investment: number | null;
    user: number | null;
    member: number | null;
    posted_by: string;
    status: string;
    from_date: string;
    to_date: string;
    min_amount: number | null;
    max_amount: number | null;
    sort: string;
    per_page: number;
};

type Props = {
    entries: {
        data: JournalEntryItem[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
        from: number | null;
        to: number | null;
    };
    summary: {
        entries: number;
        total: number;
        matched: {
            lines: number;
            debit: number;
            credit: number;
            net: number;
        } | null;
    };
    filters: Filters;
    options: {
        kinds: Option[];
        sources: Option[];
        accounts: Option[];
        account_types: Option[];
        scopes: Option[];
        cycles: Option[];
        investments: (Option & { cycle: string })[];
        users: Option[];
        members: Option[];
        posted_by: Option[];
        statuses: Option[];
        sorts: Option[];
        per_page: Option[];
    };
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Accounts', href: '/admin/accounts' },
            { title: 'Journal', href: '/admin/accounts/journal' },
        ],
    },
});

const props = defineProps<Props>();

const JOURNAL_URL = '/admin/accounts/journal';

const text = (value: string | number | null): string =>
    value === null ? '' : String(value);

const form = reactive({
    search: props.filters.search,
    kind: props.filters.kind,
    source: props.filters.source,
    account: props.filters.account,
    account_type: props.filters.account_type,
    scope: props.filters.scope,
    cycle: text(props.filters.cycle),
    investment: text(props.filters.investment),
    user: text(props.filters.user),
    member: text(props.filters.member),
    posted_by: props.filters.posted_by,
    status: props.filters.status,
    from_date: props.filters.from_date,
    to_date: props.filters.to_date,
    min_amount: text(props.filters.min_amount),
    max_amount: text(props.filters.max_amount),
    sort: props.filters.sort,
    per_page: text(props.filters.per_page),
});

type FormKey = keyof typeof form;

const advancedKeys: FormKey[] = [
    'kind',
    'source',
    'account',
    'account_type',
    'scope',
    'cycle',
    'investment',
    'user',
    'member',
    'posted_by',
    'from_date',
    'to_date',
    'min_amount',
    'max_amount',
];

const showAdvanced = ref(advancedKeys.some((key) => form[key] !== ''));

const DEFAULTS: Partial<Record<FormKey, string>> = {
    sort: 'date_desc',
    per_page: '25',
};

const query = (values: Record<string, string>) =>
    Object.fromEntries(
        Object.entries(values).filter(
            ([key, value]) =>
                value !== '' && value !== DEFAULTS[key as FormKey],
        ),
    );

const apply = () =>
    router.get(JOURNAL_URL, query({ ...form }), {
        preserveState: true,
        preserveScroll: true,
    });

const setFilter = (key: FormKey, value: string) => {
    form[key] = value;

    if (key === 'cycle') {
        form.investment = '';
    }

    apply();
};

const reset = () => router.get(JOURNAL_URL);

const findEntry = (id: number) => {
    Object.keys(form).forEach((key) => {
        form[key as FormKey] = DEFAULTS[key as FormKey] ?? '';
    });
    form.search = `#${id}`;
    apply();
};

const filterByDimension = (dimension: Dimension) => {
    if (dimension.type === 'order') {
        setFilter('search', dimension.label);

        return;
    }

    if (dimension.type === 'investment') {
        form.investment = String(dimension.id);
        apply();

        return;
    }

    setFilter(dimension.type, String(dimension.id));
};

const investmentOptions = computed(() =>
    form.cycle === ''
        ? props.options.investments
        : props.options.investments.filter(
              (investment) => investment.cycle === form.cycle,
          ),
);

const appliedQuery = computed(() => {
    const filters = props.filters;

    return query({
        search: filters.search,
        kind: filters.kind,
        source: filters.source,
        account: filters.account,
        account_type: filters.account_type,
        scope: filters.scope,
        cycle: text(filters.cycle),
        investment: text(filters.investment),
        user: text(filters.user),
        member: text(filters.member),
        posted_by: filters.posted_by,
        status: filters.status,
        from_date: filters.from_date,
        to_date: filters.to_date,
        min_amount: text(filters.min_amount),
        max_amount: text(filters.max_amount),
        sort: filters.sort,
    });
});

const exportUrl = computed(() => {
    const params = new URLSearchParams(appliedQuery.value).toString();

    return `${JOURNAL_URL}/export${params ? `?${params}` : ''}`;
});

const labelFrom = (options: Option[], value: string): string =>
    options.find((option) => option.value === value)?.label ?? value;

const chipLabels: Partial<Record<FormKey, (value: string) => string>> = {
    search: (value) => `Search: ${value}`,
    kind: (value) => `Kind: ${value}`,
    source: (value) => `Source: ${labelFrom(props.options.sources, value)}`,
    account: (value) => `Account: ${labelFrom(props.options.accounts, value)}`,
    account_type: (value) =>
        `Type: ${labelFrom(props.options.account_types, value)}`,
    scope: (value) => `Scope: ${labelFrom(props.options.scopes, value)}`,
    cycle: (value) => `Cycle: ${labelFrom(props.options.cycles, value)}`,
    investment: (value) =>
        `Project: ${labelFrom(props.options.investments, value)}`,
    user: (value) => `User: ${labelFrom(props.options.users, value)}`,
    member: (value) => `Member: ${labelFrom(props.options.members, value)}`,
    posted_by: (value) =>
        `Posted by: ${labelFrom(props.options.posted_by, value)}`,
    from_date: (value) => `From: ${value}`,
    to_date: (value) => `To: ${value}`,
    min_amount: (value) => `Min: ${value} BDT`,
    max_amount: (value) => `Max: ${value} BDT`,
};

const activeChips = computed(() =>
    Object.entries(appliedQuery.value)
        .filter(([key]) => chipLabels[key as FormKey])
        .map(([key, value]) => ({
            key: key as FormKey,
            label: chipLabels[key as FormKey]!(value),
        })),
);

const hasLineFilters = computed(() => props.summary.matched !== null);

const money = (amount: number): string =>
    amount.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

const cell = (amount: number): string => (amount ? money(amount) : '');

const dimensionPrefix: Record<Dimension['type'], string> = {
    user: 'User',
    member: 'Member',
    cycle: 'Cycle',
    investment: 'Project',
    order: 'Order',
};

const statusTabs = computed<Option[]>(() => [
    { value: '', label: 'All' },
    ...props.options.statuses,
]);

const fieldClass =
    'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';
const labelClass = 'mb-1 block text-xs font-medium text-muted-foreground';
</script>

<template>
    <Head title="Journal" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        Journal
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Every money movement, immutable. Corrections appear as
                        reversal entries.
                    </p>
                </div>
                <Button variant="outline" size="sm" as-child>
                    <a :href="exportUrl">
                        <Download class="size-4" />
                        Export CSV
                    </a>
                </Button>
            </div>

            <form class="mt-5 space-y-4" @submit.prevent="apply">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative min-w-64 flex-1">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        />
                        <Input
                            v-model="form.search"
                            placeholder="Search #entry, description, user, member, order no, phone, memo…"
                            class="pl-9"
                        />
                    </div>
                    <Button type="submit" size="sm">
                        <Search class="size-4" />
                        Search
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="showAdvanced ? 'secondary' : 'outline'"
                        @click="showAdvanced = !showAdvanced"
                    >
                        <Filter class="size-4" />
                        Filters
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        @click="reset"
                    >
                        <RotateCcw class="size-4" />
                        Reset
                    </Button>
                </div>

                <div class="flex flex-wrap gap-1">
                    <Button
                        v-for="tab in statusTabs"
                        :key="tab.value"
                        type="button"
                        size="sm"
                        :variant="
                            props.filters.status === tab.value
                                ? 'default'
                                : 'outline'
                        "
                        @click="setFilter('status', tab.value)"
                    >
                        {{ tab.label }}
                    </Button>
                </div>

                <div
                    v-if="showAdvanced"
                    class="grid gap-3 rounded-lg border border-sidebar-border/70 p-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <label>
                        <span :class="labelClass">Kind</span>
                        <select v-model="form.kind" :class="fieldClass">
                            <option value="">All kinds</option>
                            <option
                                v-for="option in props.options.kinds"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span :class="labelClass">Source document</span>
                        <select v-model="form.source" :class="fieldClass">
                            <option value="">All sources</option>
                            <option
                                v-for="option in props.options.sources"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span :class="labelClass">Account</span>
                        <select v-model="form.account" :class="fieldClass">
                            <option value="">All accounts</option>
                            <option
                                v-for="option in props.options.accounts"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label>
                            <span :class="labelClass">Account type</span>
                            <select
                                v-model="form.account_type"
                                :class="fieldClass"
                            >
                                <option value="">Any</option>
                                <option
                                    v-for="option in props.options
                                        .account_types"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <label>
                            <span :class="labelClass">Scope</span>
                            <select v-model="form.scope" :class="fieldClass">
                                <option value="">Any</option>
                                <option
                                    v-for="option in props.options.scopes"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <label>
                        <span :class="labelClass">Fund cycle</span>
                        <select
                            v-model="form.cycle"
                            :class="fieldClass"
                            @change="form.investment = ''"
                        >
                            <option value="">All cycles</option>
                            <option
                                v-for="option in props.options.cycles"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span :class="labelClass"
                            >Project (event / business)</span
                        >
                        <select v-model="form.investment" :class="fieldClass">
                            <option value="">All projects</option>
                            <option
                                v-for="option in investmentOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span :class="labelClass">User</span>
                        <select v-model="form.user" :class="fieldClass">
                            <option value="">All users</option>
                            <option
                                v-for="option in props.options.users"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span :class="labelClass">Member</span>
                        <select v-model="form.member" :class="fieldClass">
                            <option value="">All members</option>
                            <option
                                v-for="option in props.options.members"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <label>
                        <span :class="labelClass">Posted by</span>
                        <select v-model="form.posted_by" :class="fieldClass">
                            <option value="">Anyone</option>
                            <option
                                v-for="option in props.options.posted_by"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </label>
                    <div class="grid grid-cols-2 gap-2">
                        <label>
                            <span :class="labelClass">From date</span>
                            <Input v-model="form.from_date" type="date" />
                        </label>
                        <label>
                            <span :class="labelClass">To date</span>
                            <Input v-model="form.to_date" type="date" />
                        </label>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label>
                            <span :class="labelClass">Min amount</span>
                            <Input
                                v-model="form.min_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="BDT"
                            />
                        </label>
                        <label>
                            <span :class="labelClass">Max amount</span>
                            <Input
                                v-model="form.max_amount"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="BDT"
                            />
                        </label>
                    </div>
                    <div class="flex items-end sm:col-span-2 lg:col-span-1">
                        <Button type="submit" size="sm" class="w-full">
                            Apply filters
                        </Button>
                    </div>
                </div>
            </form>

            <div
                v-if="activeChips.length > 0"
                class="mt-4 flex flex-wrap items-center gap-2"
            >
                <Badge
                    v-for="chip in activeChips"
                    :key="chip.key"
                    variant="secondary"
                    class="gap-1 pr-1"
                >
                    {{ chip.label }}
                    <button
                        type="button"
                        class="rounded-sm p-0.5 hover:bg-muted-foreground/20"
                        :aria-label="`Remove ${chip.label}`"
                        @click="setFilter(chip.key, '')"
                    >
                        <X class="size-3" />
                    </button>
                </Badge>
            </div>
        </section>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">Entries</p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ props.summary.entries.toLocaleString() }}
                </p>
            </div>
            <div
                class="rounded-xl border border-sidebar-border/70 bg-background p-4 shadow-sm dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">
                    Total of entries (debit side)
                </p>
                <p class="mt-1 text-xl font-semibold tabular-nums">
                    {{ money(props.summary.total) }}
                </p>
            </div>
            <template v-if="props.summary.matched">
                <div
                    class="rounded-xl border border-primary/30 bg-background p-4 shadow-sm"
                >
                    <p class="text-xs text-muted-foreground">
                        Matching lines · debit
                    </p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ money(props.summary.matched.debit) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-primary/30 bg-background p-4 shadow-sm"
                >
                    <p class="text-xs text-muted-foreground">
                        Matching lines · credit
                    </p>
                    <p class="mt-1 text-xl font-semibold tabular-nums">
                        {{ money(props.summary.matched.credit) }}
                    </p>
                </div>
                <div
                    class="rounded-xl border border-primary/30 bg-background p-4 shadow-sm"
                >
                    <p class="text-xs text-muted-foreground">
                        Net (debit − credit) ·
                        {{ props.summary.matched.lines }} lines
                    </p>
                    <p
                        class="mt-1 text-xl font-semibold tabular-nums"
                        :class="
                            props.summary.matched.net < 0
                                ? 'text-red-600 dark:text-red-400'
                                : ''
                        "
                    >
                        {{ money(props.summary.matched.net) }}
                    </p>
                </div>
            </template>
        </section>

        <div
            class="flex flex-wrap items-center justify-between gap-2 text-sm text-muted-foreground"
        >
            <span v-if="props.entries.total > 0">
                Showing {{ props.entries.from }}–{{ props.entries.to }} of
                {{ props.entries.total.toLocaleString() }}
                <template v-if="hasLineFilters">
                    · highlighted lines match the account / dimension filters
                </template>
            </span>
            <span v-else>No entries</span>
            <div class="flex items-center gap-2">
                <select
                    v-model="form.sort"
                    class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                    aria-label="Sort"
                    @change="apply"
                >
                    <option
                        v-for="option in props.options.sorts"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <select
                    v-model="form.per_page"
                    class="h-8 rounded-md border border-input bg-transparent px-2 text-sm"
                    aria-label="Entries per page"
                    @change="apply"
                >
                    <option
                        v-for="option in props.options.per_page"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }} / page
                    </option>
                </select>
            </div>
        </div>

        <section class="space-y-3">
            <article
                v-for="entry in props.entries.data"
                :key="entry.id"
                class="overflow-hidden rounded-xl border bg-background shadow-sm"
                :class="
                    entry.reversed_by_id || entry.reversal_of_id
                        ? 'border-dashed border-sidebar-border dark:border-sidebar-border'
                        : 'border-sidebar-border/70 dark:border-sidebar-border'
                "
            >
                <header
                    class="flex flex-wrap items-center gap-2 border-b border-sidebar-border/70 px-4 py-2 text-sm"
                >
                    <button
                        type="button"
                        class="font-mono text-xs text-muted-foreground hover:text-foreground hover:underline"
                        :title="entry.idempotency_key"
                        @click="findEntry(entry.id)"
                    >
                        #{{ entry.id }}
                    </button>
                    <span class="font-medium">{{ entry.entry_date }}</span>
                    <button
                        type="button"
                        @click="setFilter('kind', entry.kind)"
                    >
                        <Badge variant="outline" class="hover:bg-muted">
                            {{ entry.kind }}
                        </Badge>
                    </button>
                    <button
                        v-if="entry.reversal_of_id"
                        type="button"
                        @click="findEntry(entry.reversal_of_id)"
                    >
                        <Badge variant="destructive">
                            reverses #{{ entry.reversal_of_id }}
                        </Badge>
                    </button>
                    <button
                        v-if="entry.reversed_by_id"
                        type="button"
                        @click="findEntry(entry.reversed_by_id)"
                    >
                        <Badge
                            variant="secondary"
                            class="text-amber-700 dark:text-amber-400"
                        >
                            reversed by #{{ entry.reversed_by_id }}
                        </Badge>
                    </button>
                    <span>{{ entry.description }}</span>
                    <span
                        class="ml-auto flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                    >
                        <template v-if="entry.source">
                            <Link
                                v-if="entry.source.url"
                                :href="entry.source.url"
                                class="inline-flex items-center gap-1 hover:text-foreground hover:underline"
                            >
                                {{ entry.source.label }}
                                <ExternalLink class="size-3" />
                            </Link>
                            <span v-else>{{ entry.source.label }}</span>
                            ·
                        </template>
                        {{ entry.posted_by || 'System' }} ·
                        {{ entry.posted_at }}
                    </span>
                </header>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead
                            class="bg-muted/40 text-xs text-muted-foreground"
                        >
                            <tr>
                                <th class="px-4 py-1.5 text-left font-medium">
                                    Account
                                </th>
                                <th
                                    class="w-32 px-4 py-1.5 text-right font-medium"
                                >
                                    Debit
                                </th>
                                <th
                                    class="w-32 px-4 py-1.5 text-right font-medium"
                                >
                                    Credit
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/50">
                            <tr
                                v-for="line in entry.lines"
                                :key="line.id"
                                :class="line.matches ? 'bg-primary/5' : ''"
                            >
                                <td
                                    class="px-4 py-1.5"
                                    :class="line.credit ? 'pl-10' : ''"
                                >
                                    <button
                                        type="button"
                                        class="hover:underline"
                                        @click="
                                            setFilter(
                                                'account',
                                                line.code ?? '',
                                            )
                                        "
                                    >
                                        {{ line.code }} · {{ line.account }}
                                    </button>
                                    <button
                                        v-for="dimension in line.dimensions"
                                        :key="`${dimension.type}-${dimension.id}`"
                                        type="button"
                                        class="ml-2 text-xs text-muted-foreground hover:text-foreground hover:underline"
                                        @click="filterByDimension(dimension)"
                                    >
                                        {{ dimensionPrefix[dimension.type] }}:
                                        {{ dimension.label }}
                                    </button>
                                    <span
                                        v-if="line.memo"
                                        class="ml-2 text-xs text-muted-foreground italic"
                                    >
                                        {{ line.memo }}
                                    </span>
                                </td>
                                <td class="px-4 py-1.5 text-right tabular-nums">
                                    {{ cell(line.debit) }}
                                </td>
                                <td class="px-4 py-1.5 text-right tabular-nums">
                                    {{ cell(line.credit) }}
                                </td>
                            </tr>
                        </tbody>
                        <tfoot
                            class="border-t border-sidebar-border/70 text-xs font-medium"
                        >
                            <tr>
                                <td class="px-4 py-1.5 text-muted-foreground">
                                    Total
                                </td>
                                <td class="px-4 py-1.5 text-right tabular-nums">
                                    {{ money(entry.total) }}
                                </td>
                                <td class="px-4 py-1.5 text-right tabular-nums">
                                    {{ money(entry.total) }}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </article>
            <p
                v-if="props.entries.data.length === 0"
                class="rounded-xl border border-sidebar-border/70 bg-background p-8 text-center text-sm text-muted-foreground"
            >
                No journal entries match these filters.
            </p>
        </section>

        <nav class="flex flex-wrap gap-1">
            <template v-for="link in props.entries.links" :key="link.label">
                <Button
                    v-if="link.url"
                    size="sm"
                    :variant="link.active ? 'default' : 'outline'"
                    as-child
                >
                    <Link :href="link.url" preserve-scroll>
                        <span v-html="link.label" />
                    </Link>
                </Button>
            </template>
        </nav>
    </div>
</template>
