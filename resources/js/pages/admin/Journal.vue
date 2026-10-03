<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type JournalEntryItem = {
    id: number;
    entry_date: string | null;
    kind: string;
    description: string;
    is_reversal: boolean;
    reversal_of_id: number | null;
    posted_by: string | null;
    posted_at: string | null;
    lines: {
        code: string | null;
        account: string | null;
        debit: number;
        credit: number;
        dimensions: string[];
        memo: string | null;
    }[];
};

type Props = {
    entries: {
        data: JournalEntryItem[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    filters: {
        kind: string;
        account: string;
        cycle: number | null;
        user: number | null;
        from_date: string;
        to_date: string;
    };
    accounts: { value: string; label: string }[];
    cycles: { value: string; label: string }[];
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

const filters = reactive({
    kind: props.filters.kind,
    account: props.filters.account,
    cycle: props.filters.cycle ? String(props.filters.cycle) : '',
    from_date: props.filters.from_date,
    to_date: props.filters.to_date,
});

const apply = () =>
    router.get(
        '/admin/accounts/journal',
        Object.fromEntries(
            Object.entries(filters).filter(([, value]) => value !== ''),
        ),
        { preserveState: true },
    );

const money = (amount: number): string =>
    amount
        ? amount.toLocaleString(undefined, {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
          })
        : '';

const selectClass =
    'h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs';
</script>

<template>
    <Head title="Journal" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <h1 class="text-2xl font-semibold tracking-tight">Journal</h1>
            <p class="mt-2 text-sm text-muted-foreground">
                Every money movement, immutable. Corrections appear as reversal
                entries. {{ props.entries.total }} entries.
            </p>

            <form
                class="mt-4 flex flex-wrap items-end gap-2"
                @submit.prevent="apply"
            >
                <Input
                    v-model="filters.kind"
                    placeholder="Kind (e.g. event_)"
                    class="w-44"
                />
                <select v-model="filters.account" :class="selectClass">
                    <option value="">All accounts</option>
                    <option
                        v-for="account in props.accounts"
                        :key="account.value"
                        :value="account.value"
                    >
                        {{ account.label }}
                    </option>
                </select>
                <select v-model="filters.cycle" :class="selectClass">
                    <option value="">All cycles</option>
                    <option
                        v-for="cycle in props.cycles"
                        :key="cycle.value"
                        :value="cycle.value"
                    >
                        {{ cycle.label }}
                    </option>
                </select>
                <Input v-model="filters.from_date" type="date" class="w-40" />
                <Input v-model="filters.to_date" type="date" class="w-40" />
                <Button type="submit" size="sm">Filter</Button>
            </form>
        </section>

        <section class="space-y-3">
            <div
                v-for="entry in props.entries.data"
                :key="entry.id"
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
            >
                <div
                    class="flex flex-wrap items-center gap-2 border-b border-sidebar-border/70 px-4 py-2 text-sm"
                >
                    <span class="font-mono text-xs text-muted-foreground"
                        >#{{ entry.id }}</span
                    >
                    <span class="font-medium">{{ entry.entry_date }}</span>
                    <Badge variant="outline">{{ entry.kind }}</Badge>
                    <Badge v-if="entry.is_reversal" variant="destructive">
                        reverses #{{ entry.reversal_of_id }}
                    </Badge>
                    <span>{{ entry.description }}</span>
                    <span class="ml-auto text-xs text-muted-foreground">
                        {{ entry.posted_by || 'System' }} ·
                        {{ entry.posted_at }}
                    </span>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-sidebar-border/50">
                        <tr v-for="(line, index) in entry.lines" :key="index">
                            <td
                                class="px-4 py-1.5"
                                :class="line.credit ? 'pl-10' : ''"
                            >
                                {{ line.code }} · {{ line.account }}
                                <span
                                    v-for="dimension in line.dimensions"
                                    :key="dimension"
                                    class="ml-2 text-xs text-muted-foreground"
                                >
                                    {{ dimension }}
                                </span>
                                <span
                                    v-if="line.memo"
                                    class="ml-2 text-xs text-muted-foreground italic"
                                >
                                    {{ line.memo }}
                                </span>
                            </td>
                            <td
                                class="w-32 px-4 py-1.5 text-right tabular-nums"
                            >
                                {{ money(line.debit) }}
                            </td>
                            <td
                                class="w-32 px-4 py-1.5 text-right tabular-nums"
                            >
                                {{ money(line.credit) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p
                v-if="props.entries.data.length === 0"
                class="rounded-xl border border-sidebar-border/70 bg-background p-8 text-center text-sm text-muted-foreground"
            >
                No journal entries match.
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
