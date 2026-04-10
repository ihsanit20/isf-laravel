<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Ban } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ChargeCancelDialog from '@/components/admin/ChargeCancelDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

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
                title: 'Charge List',
                href: '/admin/charges',
            },
        ],
    },
});

defineProps<Props>();

const selectedCharge = ref<AdminCharge | null>(null);
const isCancelDialogOpen = ref(false);

const cancelableCharge = computed(() => selectedCharge.value);

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const statusVariant = (
    status: ChargeStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'posted') {
        return 'default';
    }

    if (status === 'cancelled') {
        return 'destructive';
    }

    if (status === 'waived') {
        return 'outline';
    }

    return 'secondary';
};

const openCancelDialog = (charge: AdminCharge) => {
    selectedCharge.value = charge;

    isCancelDialogOpen.value = true;
};
</script>

<template>
    <Head title="Charge List" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="max-w-2xl">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Charge List
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    View member charges and cancel them when a fee needs to be
                    reversed back into the pooled deposit balance.
                </p>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                >
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Member</th>
                            <th class="px-4 py-3 font-medium">Charge</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Effective At</th>
                            <th class="px-4 py-3 font-medium">Allocated</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="charge in charges" :key="charge.id">
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{
                                        charge.member.full_name ||
                                        'Unknown member'
                                    }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ charge.member.manager_name || '-' }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ charge.member.manager_email || '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div class="font-medium text-foreground">
                                    {{ charge.category.title || '-' }}
                                </div>
                                <div class="text-xs">
                                    {{ charge.category.code || '-' }}
                                </div>
                                <div class="text-xs">
                                    {{ money(charge.amount) }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <Badge :variant="statusVariant(charge.status)">
                                    {{
                                        charge.status.charAt(0).toUpperCase() +
                                        charge.status.slice(1)
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ charge.effective_at || '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ money(charge.allocated_amount) }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    v-if="charge.status !== 'cancelled'"
                                    class="flex flex-wrap gap-2"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openCancelDialog(charge)"
                                    >
                                        <Ban class="size-4" />
                                        Cancel
                                    </Button>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                    >Reviewed</span
                                >
                            </td>
                        </tr>
                        <tr v-if="charges.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No charges found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <ChargeCancelDialog
            v-model:isOpen="isCancelDialogOpen"
            :charge="cancelableCharge"
        />
    </div>
</template>
