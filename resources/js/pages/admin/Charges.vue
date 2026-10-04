<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Ban } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ChargeCancelDialog from '@/components/admin/ChargeCancelDialog.vue';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatMoney } from '@/lib/format';

type ChargeStatus = 'pending' | 'posted' | 'waived' | 'cancelled';

type AdminCharge = {
    id: number;
    amount: number;
    status: ChargeStatus;
    effective_at: string | null;
    allocated_amount: number;
    category: {
        code: string | null;
        title: string | null;
    };
    member: {
        id: number | null;
        full_name: string | null;
        manager_name: string | null;
        manager_email: string | null;
    };
};

type Props = {
    charges: AdminCharge[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Charges',
                href: '/admin/charges',
            },
        ],
    },
});

const props = defineProps<Props>();

const selectedCharge = ref<AdminCharge | null>(null);
const isCancelDialogOpen = ref(false);

const cancelableCharge = computed(() => selectedCharge.value);

const chargeStatusLabels: Record<ChargeStatus, string> = {
    pending: 'Unpaid',
    posted: 'Paid',
    waived: 'Waived',
    cancelled: 'Cancelled',
};

const statusFilter = ref<'all' | ChargeStatus>('all');

const filters = computed(() =>
    (['all', 'pending', 'posted', 'waived', 'cancelled'] as const).map(
        (status) => ({
            value: status,
            label: status === 'all' ? 'All' : chargeStatusLabels[status],
            count:
                status === 'all'
                    ? props.charges.length
                    : props.charges.filter((charge) => charge.status === status)
                          .length,
        }),
    ),
);

const visibleCharges = computed(() =>
    statusFilter.value === 'all'
        ? props.charges
        : props.charges.filter(
              (charge) => charge.status === statusFilter.value,
          ),
);

const openCancelDialog = (charge: AdminCharge) => {
    selectedCharge.value = charge;

    isCancelDialogOpen.value = true;
};
</script>

<template>
    <Head title="Charges" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Charges"
            description="Member charges. Cancelling a paid charge returns the amount to the member’s balance."
        />

        <Tabs v-model="statusFilter">
            <TabsList class="h-auto flex-wrap">
                <TabsTrigger
                    v-for="filter in filters"
                    :key="filter.value"
                    :value="filter.value"
                >
                    {{ filter.label }}
                    <span class="text-xs text-muted-foreground">
                        {{ filter.count }}
                    </span>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Member</TableHead>
                        <TableHead>Charge</TableHead>
                        <TableHead class="text-right">Amount</TableHead>
                        <TableHead class="text-right">Paid</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Effective</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="charge in visibleCharges" :key="charge.id">
                        <TableCell class="pl-4">
                            <p class="font-medium">
                                {{
                                    charge.member.full_name || 'Unknown member'
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ charge.member.manager_name || '—' }}
                                <template v-if="charge.member.manager_email">
                                    · {{ charge.member.manager_email }}
                                </template>
                            </p>
                        </TableCell>
                        <TableCell>
                            <p>{{ charge.category.title || '—' }}</p>
                            <code class="text-xs text-muted-foreground">
                                {{ charge.category.code }}
                            </code>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ formatMoney(charge.amount) }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ formatMoney(charge.allocated_amount) }}
                        </TableCell>
                        <TableCell>
                            <StatusBadge
                                :status="charge.status"
                                :label="chargeStatusLabels[charge.status]"
                            />
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ charge.effective_at || '—' }}
                        </TableCell>
                        <TableCell class="pr-4 text-right">
                            <Button
                                v-if="charge.status !== 'cancelled'"
                                variant="outline"
                                size="sm"
                                @click="openCancelDialog(charge)"
                            >
                                <Ban class="size-4" />
                                Cancel
                            </Button>
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="visibleCharges.length === 0" :colspan="7">
                        No charges here.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

        <ChargeCancelDialog
            v-model:isOpen="isCancelDialogOpen"
            :charge="cancelableCharge"
        />
    </div>
</template>
