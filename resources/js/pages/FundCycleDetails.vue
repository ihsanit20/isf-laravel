<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Check } from 'lucide-vue-next';
import { computed } from 'vue';
import PageHeader from '@/components/shared/PageHeader.vue';
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
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { amountToneClass, formatMoney, formatSignedMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

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
    type?: 'event' | 'business';
    title: string;
    total_income_amount: number;
    total_expense_amount: number;
    net_profit_amount: number;
};

type CycleResult = {
    investments_result: number;
    cycle_income: number;
    cycle_expense: number;
    result: number;
    is_settled: boolean;
    open_investments: number;
    my_capital: number;
    my_share: number;
    my_payout: number;
};

type MyMemberRow = {
    member_id: number;
    name: string;
    capital: number;
    share: number;
    payout: number;
};

type MyAllocation = {
    id: number;
    member_name: string | null;
    slot_key: string | null;
    amount: number;
    allocated_at: string | null;
};

type Props = {
    fundCycle: FundCycleItem;
    events: EventItem[];
    cycleResult: CycleResult;
    myMembers: MyMemberRow[];
    myAllocations: MyAllocation[];
};

const props = defineProps<Props>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Fund Cycles', href: '/fund-cycles' },
            { title: 'Cycle Details', href: '#' },
        ],
    },
});

const isSettled = computed(() => props.cycleResult.is_settled);

const stages = computed(() => {
    const order = ['open', 'locked', 'matured', 'settled'];
    const current = order.indexOf(props.fundCycle.status);

    return [
        { label: 'Open', date: props.fundCycle.start_date },
        { label: 'Locked', date: props.fundCycle.lock_date },
        { label: 'Matured', date: props.fundCycle.maturity_date },
        { label: 'Settled', date: props.fundCycle.settlement_date },
    ].map((stage, index) => ({
        ...stage,
        done: index < current || props.fundCycle.status === 'settled',
        current: index === current && props.fundCycle.status !== 'settled',
    }));
});

const eventTotals = computed(() => ({
    income: props.events.reduce(
        (sum, event) => sum + event.total_income_amount,
        0,
    ),
    expense: props.events.reduce(
        (sum, event) => sum + event.total_expense_amount,
        0,
    ),
    net: props.events.reduce((sum, event) => sum + event.net_profit_amount, 0),
}));

const hasCycleLevelLines = computed(
    () =>
        props.cycleResult.cycle_income !== 0 ||
        props.cycleResult.cycle_expense !== 0,
);
</script>

<template>
    <Head :title="props.fundCycle.name" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="props.fundCycle.name"
            :description="`${formatMoney(props.fundCycle.unit_amount)} per unit per month · ${props.fundCycle.allocations_count} allocations`"
        >
            <template #actions>
                <Button as-child variant="outline">
                    <Link href="/fund-cycles">
                        <ArrowLeft class="size-4" />
                        All cycles
                    </Link>
                </Button>
                <Button v-if="props.fundCycle.status === 'open'" as-child>
                    <Link href="/my-allocations">Invest</Link>
                </Button>
            </template>
        </PageHeader>

        <ol class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <li
                v-for="stage in stages"
                :key="stage.label"
                :class="
                    cn(
                        'flex items-center gap-2 rounded-lg border px-3 py-2',
                        stage.current && 'border-primary/40 bg-primary/5',
                    )
                "
            >
                <span
                    :class="
                        cn(
                            'flex size-6 shrink-0 items-center justify-center rounded-full border text-xs',
                            stage.done &&
                                'border-emerald-600 bg-emerald-600 text-white dark:border-emerald-500 dark:bg-emerald-500',
                            stage.current && 'border-primary',
                        )
                    "
                >
                    <Check v-if="stage.done" class="size-3.5" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-medium">{{ stage.label }}</p>
                    <p class="truncate text-xs text-muted-foreground">
                        {{ stage.date ?? '—' }}
                    </p>
                </div>
            </li>
        </ol>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <StatCard
                label="Cycle total"
                :value="formatMoney(props.fundCycle.total_allocated_amount)"
                hint="Capital from all members"
            />
            <StatCard
                :label="isSettled ? 'Cycle result' : 'Cycle result so far'"
                :value="formatSignedMoney(props.cycleResult.result)"
                :value-class="amountToneClass(props.cycleResult.result)"
                :hint="
                    props.cycleResult.open_investments > 0
                        ? `${props.cycleResult.open_investments} event(s) still running`
                        : 'All events finalized'
                "
            />
            <StatCard
                label="My investment"
                :value="formatMoney(props.cycleResult.my_capital)"
                hint="All my members together"
            />
            <StatCard
                :label="isSettled ? 'Returned to me' : 'My share so far'"
                :value="
                    isSettled
                        ? formatMoney(props.cycleResult.my_payout)
                        : formatSignedMoney(props.cycleResult.my_share)
                "
                :value-class="
                    isSettled ? '' : amountToneClass(props.cycleResult.my_share)
                "
                :hint="
                    isSettled
                        ? `Profit / loss ${formatSignedMoney(props.cycleResult.my_share)}`
                        : 'Split by capital ratio'
                "
            />
        </div>

        <Card class="gap-4">
            <CardHeader>
                <CardTitle>Finalized events</CardTitle>
                <CardDescription>
                    Events and businesses this cycle invested in, once they are
                    closed.
                </CardDescription>
            </CardHeader>
            <CardContent class="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12 pl-6">SL</TableHead>
                            <TableHead>Event</TableHead>
                            <TableHead class="text-right">
                                Total income
                            </TableHead>
                            <TableHead class="text-right">
                                Total expenses
                            </TableHead>
                            <TableHead class="pr-6 text-right">
                                Net income
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(event, index) in props.events"
                            :key="event.id"
                        >
                            <TableCell
                                class="pl-6 text-muted-foreground tabular-nums"
                                >{{ index + 1 }}</TableCell
                            >
                            <TableCell class="whitespace-normal">
                                <p class="font-medium">{{ event.title }}</p>
                                <p
                                    v-if="event.type === 'business'"
                                    class="text-xs text-muted-foreground"
                                >
                                    Business
                                </p>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(event.total_income_amount) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(event.total_expense_amount) }}
                            </TableCell>
                            <TableCell
                                class="pr-6 text-right font-medium tabular-nums"
                                :class="
                                    amountToneClass(event.net_profit_amount)
                                "
                            >
                                {{ formatSignedMoney(event.net_profit_amount) }}
                            </TableCell>
                        </TableRow>
                        <TableEmpty
                            v-if="props.events.length === 0"
                            :colspan="5"
                        >
                            No event has been finalized yet.
                        </TableEmpty>
                    </TableBody>
                    <TableFooter v-if="props.events.length > 0">
                        <TableRow>
                            <TableCell colspan="2" class="pl-6"
                                >Total</TableCell
                            >
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(eventTotals.income) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(eventTotals.expense) }}
                            </TableCell>
                            <TableCell
                                class="pr-6 text-right tabular-nums"
                                :class="amountToneClass(eventTotals.net)"
                            >
                                {{ formatSignedMoney(eventTotals.net) }}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>

                <dl
                    v-if="hasCycleLevelLines"
                    class="mx-6 mt-4 grid gap-1 text-sm"
                >
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Events net</dt>
                        <dd class="tabular-nums">
                            {{
                                formatSignedMoney(
                                    props.cycleResult.investments_result,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Other cycle income
                        </dt>
                        <dd class="tabular-nums">
                            {{
                                formatSignedMoney(
                                    props.cycleResult.cycle_income,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">
                            Other cycle expenses
                        </dt>
                        <dd class="tabular-nums">
                            {{
                                formatSignedMoney(
                                    -props.cycleResult.cycle_expense,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between border-t pt-1 font-medium">
                        <dt>Cycle result</dt>
                        <dd
                            class="tabular-nums"
                            :class="amountToneClass(props.cycleResult.result)"
                        >
                            {{ formatSignedMoney(props.cycleResult.result) }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <Card v-if="props.myMembers.length > 0" class="gap-4">
            <CardHeader>
                <CardTitle>
                    {{ isSettled ? 'Settlement' : 'My members in this cycle' }}
                </CardTitle>
                <CardDescription>
                    {{
                        isSettled
                            ? 'What each of my members invested and what came back to my balance.'
                            : 'What each of my members invested. The share is what the result so far would give; it is final only at settlement.'
                    }}
                </CardDescription>
            </CardHeader>
            <CardContent class="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12 pl-6">SL</TableHead>
                            <TableHead>Member</TableHead>
                            <TableHead class="text-right">Invested</TableHead>
                            <TableHead class="text-right">
                                Profit / Loss
                            </TableHead>
                            <TableHead class="pr-6 text-right">
                                {{ isSettled ? 'Returned' : 'Would return' }}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(member, index) in props.myMembers"
                            :key="member.member_id"
                        >
                            <TableCell
                                class="pl-6 text-muted-foreground tabular-nums"
                                >{{ index + 1 }}</TableCell
                            >
                            <TableCell class="font-medium">
                                {{ member.name }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(member.capital) }}
                            </TableCell>
                            <TableCell
                                class="text-right tabular-nums"
                                :class="amountToneClass(member.share)"
                            >
                                {{ formatSignedMoney(member.share) }}
                            </TableCell>
                            <TableCell
                                :class="
                                    cn(
                                        'pr-6 text-right tabular-nums',
                                        isSettled
                                            ? 'font-medium'
                                            : 'text-muted-foreground',
                                    )
                                "
                            >
                                {{ formatMoney(member.payout) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                    <TableFooter v-if="props.myMembers.length > 1">
                        <TableRow>
                            <TableCell colspan="2" class="pl-6"
                                >Total</TableCell
                            >
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(props.cycleResult.my_capital) }}
                            </TableCell>
                            <TableCell
                                class="text-right tabular-nums"
                                :class="
                                    amountToneClass(props.cycleResult.my_share)
                                "
                            >
                                {{
                                    formatSignedMoney(
                                        props.cycleResult.my_share,
                                    )
                                }}
                            </TableCell>
                            <TableCell class="pr-6 text-right tabular-nums">
                                {{ formatMoney(props.cycleResult.my_payout) }}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>
            </CardContent>
        </Card>

        <Card v-if="props.myAllocations.length > 0" class="gap-4">
            <CardHeader>
                <CardTitle>My allocations</CardTitle>
            </CardHeader>
            <CardContent class="px-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12 pl-6">SL</TableHead>
                            <TableHead>Member</TableHead>
                            <TableHead>Month</TableHead>
                            <TableHead class="text-right">Amount</TableHead>
                            <TableHead class="pr-6">Date</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(allocation, index) in props.myAllocations"
                            :key="allocation.id"
                        >
                            <TableCell
                                class="pl-6 text-muted-foreground tabular-nums"
                                >{{ index + 1 }}</TableCell
                            >
                            <TableCell>
                                {{ allocation.member_name }}
                            </TableCell>
                            <TableCell>{{ allocation.slot_key }}</TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(allocation.amount) }}
                            </TableCell>
                            <TableCell class="pr-6 text-muted-foreground">
                                {{ allocation.allocated_at }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    </div>
</template>
