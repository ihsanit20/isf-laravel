<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import FundCycleFormDialog from '@/components/admin/FundCycleFormDialog.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney } from '@/lib/format';

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
    slots: string[];
    notes: string | null;
    has_allocations: boolean;
    created_by: string | null;
    created_at: string | null;
    allocated_amount: number;
    allocations_count: number;
};

type Props = {
    fundCycles: FundCycleItem[];
    statuses: string[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Fund Cycles',
                href: '/admin/fund-cycles',
            },
        ],
    },
});

const props = defineProps<Props>();
const isCreateDialogOpen = ref(false);
</script>

<template>
    <Head title="Fund Cycles" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Fund cycles"
            description="Each cycle collects member capital month by month, invests it in events and businesses, then settles back to members."
        >
            <template #actions>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    New fund cycle
                </Button>
            </template>
        </PageHeader>

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12 pl-4">SL</TableHead>
                        <TableHead>Cycle</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead class="text-right">Unit amount</TableHead>
                        <TableHead>Timeline</TableHead>
                        <TableHead class="text-right">Allocated</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(fundCycle, index) in fundCycles"
                        :key="fundCycle.id"
                    >
                        <TableCell
                            class="pl-4 text-muted-foreground tabular-nums"
                            >{{ index + 1 }}</TableCell
                        >
                        <TableCell class="max-w-xs whitespace-normal">
                            <Link
                                :href="`/admin/fund-cycles/${fundCycle.id}`"
                                class="font-medium hover:underline"
                            >
                                {{ fundCycle.name }}
                            </Link>
                            <p class="text-xs text-muted-foreground">
                                {{ fundCycle.slots.length }} month(s)
                                <template v-if="fundCycle.slots.length > 0">
                                    · {{ fundCycle.slots[0] }} –
                                    {{
                                        fundCycle.slots[
                                            fundCycle.slots.length - 1
                                        ]
                                    }}
                                </template>
                            </p>
                            <p
                                v-if="fundCycle.notes"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ fundCycle.notes }}
                            </p>
                        </TableCell>
                        <TableCell>
                            <StatusBadge
                                :status="fundCycle.status"
                                :label="fundCycle.status_label"
                            />
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ formatMoney(fundCycle.unit_amount) }}
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground">
                            <p>Start {{ fundCycle.start_date || '—' }}</p>
                            <p>Lock {{ fundCycle.lock_date || '—' }}</p>
                            <p>Maturity {{ fundCycle.maturity_date || '—' }}</p>
                            <p>
                                Settlement
                                {{ fundCycle.settlement_date || '—' }}
                            </p>
                        </TableCell>
                        <TableCell class="text-right">
                            <p class="font-medium tabular-nums">
                                {{ formatMoney(fundCycle.allocated_amount) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ fundCycle.allocations_count }} entries
                            </p>
                        </TableCell>
                        <TableCell class="pr-4">
                            <div class="flex justify-end gap-2">
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="`/admin/fund-cycles/${fundCycle.id}/allocations`"
                                    >
                                        Allocations
                                    </Link>
                                </Button>
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="`/admin/fund-cycles/${fundCycle.id}/events`"
                                    >
                                        Events
                                    </Link>
                                </Button>
                                <Button size="sm" as-child>
                                    <Link
                                        :href="`/admin/fund-cycles/${fundCycle.id}`"
                                    >
                                        Details
                                    </Link>
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="fundCycles.length === 0" :colspan="7">
                        No fund cycles yet.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

        <FundCycleFormDialog
            v-model:isOpen="isCreateDialogOpen"
            mode="create"
            :statuses="props.statuses"
        />
    </div>
</template>
