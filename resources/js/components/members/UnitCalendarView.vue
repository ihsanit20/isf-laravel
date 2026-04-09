<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CalendarDays,
    ReceiptText,
    UserRound,
    WalletCards,
} from 'lucide-vue-next';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type MemberSummary = {
    id: number;
    full_name: string;
    relationship_to_user: string;
    phone: string | null;
    units: number;
    status: 'pending' | 'approved' | 'rejected' | 'exited';
    manager: {
        name: string | null;
        email: string | null;
    };
};

type CalendarEntry = {
    id: number;
    units: number;
    amount: number;
    confirmed_at: string | null;
};

type CalendarMonth = {
    month_number: number;
    month_key: string;
    month_label: string;
    is_paid: boolean;
    total_units: number;
    total_amount: number;
    entries: CalendarEntry[];
};

type Props = {
    member: MemberSummary;
    selectedYear: number;
    availableYears: number[];
    yearlySummary: {
        total_units: number;
        total_amount: number;
        paid_months: number;
    };
    months: CalendarMonth[];
    backUrl: string;
    backLabel: string;
    calendarBaseUrl: string;
};

const props = defineProps<Props>();

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const relationshipLabel = computed(() =>
    props.member.relationship_to_user
        .replace('_', ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase()),
);

const statusVariant = computed<
    'default' | 'secondary' | 'destructive' | 'outline'
>(() => {
    if (props.member.status === 'approved') {
        return 'default';
    }

    if (props.member.status === 'rejected') {
        return 'destructive';
    }

    if (props.member.status === 'exited') {
        return 'outline';
    }

    return 'secondary';
});
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-sky-50 p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Annual Unit Calendar
                    </p>
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <h1 class="text-3xl font-semibold tracking-tight">
                            {{ member.full_name }}
                        </h1>
                        <Badge :variant="statusVariant">
                            {{
                                member.status.replace(/\b\w/g, (char) =>
                                    char.toUpperCase(),
                                )
                            }}
                        </Badge>
                    </div>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        Review the month-by-month paid units for this member in
                        {{ selectedYear }}.
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link :href="backUrl">
                        <ArrowLeft class="size-4" />
                        {{ backLabel }}
                    </Link>
                </Button>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-[0.95fr_1.05fr]">
            <div class="space-y-4">
                <section
                    class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        Member Summary
                    </h2>

                    <div class="mt-4 grid gap-3 text-sm">
                        <div
                            class="flex items-center gap-3 rounded-2xl bg-muted/30 px-4 py-4"
                        >
                            <UserRound class="size-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Relationship
                                </p>
                                <p class="font-medium text-foreground">
                                    {{ relationshipLabel }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-3 rounded-2xl bg-muted/30 px-4 py-4"
                        >
                            <WalletCards class="size-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Monthly units
                                </p>
                                <p class="font-medium text-foreground">
                                    {{ member.units }} unit{{
                                        member.units > 1 ? 's' : ''
                                    }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-3 rounded-2xl bg-muted/30 px-4 py-4"
                        >
                            <ReceiptText class="size-4 text-muted-foreground" />
                            <div>
                                <p class="text-xs text-muted-foreground">
                                    Phone
                                </p>
                                <p class="font-medium text-foreground">
                                    {{ member.phone || 'Not set' }}
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="member.manager.name || member.manager.email"
                            class="rounded-2xl border border-dashed border-border/70 bg-background px-4 py-4"
                        >
                            <p class="text-xs text-muted-foreground">
                                Managed By
                            </p>
                            <p class="mt-2 font-medium text-foreground">
                                {{ member.manager.name || '-' }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ member.manager.email || '-' }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        {{ selectedYear }} Summary
                    </h2>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Paid months
                            </p>
                            <p
                                class="mt-2 text-lg font-semibold text-foreground"
                            >
                                {{ yearlySummary.paid_months }} / 12
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Total units
                            </p>
                            <p
                                class="mt-2 text-lg font-semibold text-foreground"
                            >
                                {{ yearlySummary.total_units }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Total amount
                            </p>
                            <p
                                class="mt-2 text-lg font-semibold text-foreground"
                            >
                                {{ money(yearlySummary.total_amount) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Link
                            v-for="year in availableYears"
                            :key="year"
                            :href="`${calendarBaseUrl}?year=${year}`"
                        >
                            <Button
                                :variant="
                                    year === selectedYear
                                        ? 'default'
                                        : 'outline'
                                "
                                size="sm"
                            >
                                {{ year }}
                            </Button>
                        </Link>
                    </div>
                </section>
            </div>

            <section
                class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
            >
                <div class="flex items-center gap-2">
                    <CalendarDays class="size-5 text-muted-foreground" />
                    <h2 class="text-lg font-semibold tracking-tight">
                        Calendar View
                    </h2>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    <article
                        v-for="month in months"
                        :key="month.month_key"
                        class="rounded-3xl border p-4 shadow-sm"
                        :class="
                            month.is_paid
                                ? 'border-emerald-200 bg-linear-to-br from-emerald-50 via-background to-background'
                                : 'border-border/70 bg-muted/15'
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3
                                    class="text-lg font-semibold tracking-tight text-foreground"
                                >
                                    {{ month.month_label }}
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    {{ selectedYear }}
                                </p>
                            </div>

                            <Badge
                                :variant="month.is_paid ? 'default' : 'outline'"
                            >
                                {{ month.is_paid ? 'Paid' : 'No Payment' }}
                            </Badge>
                        </div>

                        <div class="mt-4 grid gap-2 text-sm">
                            <div
                                class="flex items-center justify-between rounded-2xl bg-background/80 px-3 py-3"
                            >
                                <span class="text-muted-foreground">Units</span>
                                <span class="font-medium text-foreground">{{
                                    month.total_units
                                }}</span>
                            </div>
                            <div
                                class="flex items-center justify-between rounded-2xl bg-background/80 px-3 py-3"
                            >
                                <span class="text-muted-foreground"
                                    >Amount</span
                                >
                                <span class="font-medium text-foreground">{{
                                    money(month.total_amount)
                                }}</span>
                            </div>
                        </div>

                        <div
                            v-if="month.entries.length > 0"
                            class="mt-4 space-y-2"
                        >
                            <div
                                v-for="entry in month.entries"
                                :key="entry.id"
                                class="rounded-2xl border border-border/70 bg-background/80 px-3 py-3 text-sm"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <p class="font-medium text-foreground">
                                        {{ entry.units }} unit{{
                                            entry.units > 1 ? 's' : ''
                                        }}
                                    </p>
                                    <p class="font-medium text-foreground">
                                        {{ money(entry.amount) }}
                                    </p>
                                </div>
                                <div
                                    class="mt-2 space-y-1 text-xs text-muted-foreground"
                                >
                                    <p>
                                        Confirmed at:
                                        {{ entry.confirmed_at || '-' }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <p
                            v-else
                            class="mt-4 text-sm leading-6 text-muted-foreground"
                        >
                            No paid units were recorded for this month.
                        </p>
                    </article>
                </div>
            </section>
        </section>
    </div>
</template>
