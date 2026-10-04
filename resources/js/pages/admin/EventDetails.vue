<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ImageUp, ListOrdered, Lock, Pencil, Tag } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EventAccountsTab from '@/components/admin/event-details/EventAccountsTab.vue';
import EventBankDepositsTab from '@/components/admin/event-details/EventBankDepositsTab.vue';
import EventCostsTab from '@/components/admin/event-details/EventCostsTab.vue';
import EventOverviewTab from '@/components/admin/event-details/EventOverviewTab.vue';
import EventPackagesTab from '@/components/admin/event-details/EventPackagesTab.vue';
import EventPaymentsTab from '@/components/admin/event-details/EventPaymentsTab.vue';
import EventPickupTab from '@/components/admin/event-details/EventPickupTab.vue';
import EventWithdrawalsTab from '@/components/admin/event-details/EventWithdrawalsTab.vue';
import type {
    EventDetails,
    Option,
    OrderSummary,
} from '@/components/admin/event-details/types';
import FundCycleEventFormDialog from '@/components/admin/FundCycleEventFormDialog.vue';
import type { InvestmentLedger } from '@/components/admin/InvestmentLedgerPanel.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

type Props = {
    event: EventDetails;
    orderSummary: OrderSummary;
    statusOptions: Option[];
    eventStatuses: Option[];
    packageStatuses: Option[];
    packageUnitTypes: Option[];
    expenseCategories: Option[];
    incomeCategories: Option[];
    ledger: InvestmentLedger;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Events',
                href: '/admin/events',
            },
            {
                title: 'Event Details',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();

type DetailTab =
    | 'details'
    | 'packages'
    | 'pickup'
    | 'payments'
    | 'deposits'
    | 'withdrawals'
    | 'costs'
    | 'accounts';

const tabs = computed<{ key: DetailTab; label: string; count?: number }[]>(
    () => [
        { key: 'details', label: 'Details' },
        {
            key: 'packages',
            label: 'Packages',
            count: props.event.packages.length,
        },
        {
            key: 'pickup',
            label: 'Pickup points',
            count: props.event.pickup_points.length,
        },
        {
            key: 'payments',
            label: 'Payments',
            count: props.event.payment_summary.entry_count,
        },
        {
            key: 'deposits',
            label: 'Bank deposits',
            count: props.event.bank_deposit_summary.entry_count,
        },
        {
            key: 'withdrawals',
            label: 'Bank withdrawals',
            count: props.event.withdrawal_summary.entry_count,
        },
        {
            key: 'costs',
            label: 'Costs',
            count: props.event.expense_summary.entry_count,
        },
        { key: 'accounts', label: 'Accounts & result' },
    ],
);

const page = usePage();
const activeTab = ref<DetailTab>('details');

const setActiveTab = (value: string | number) => {
    const tab = String(value) as DetailTab;
    activeTab.value = tab;

    const url = new URL(page.url, window.location.origin);

    if (tab === 'details') {
        url.searchParams.delete('tab');
    } else {
        url.searchParams.set('tab', tab);
    }

    window.history.replaceState({}, '', `${url.pathname}${url.search}`);
};

watch(
    () => page.url,
    () => {
        const tab = new URL(page.url, window.location.origin).searchParams.get(
            'tab',
        );

        if (tab && tabs.value.some((item) => item.key === tab)) {
            activeTab.value = tab as DetailTab;
        }
    },
    { immediate: true },
);

const isEditDialogOpen = ref(false);

const editableEvent = computed(() => ({
    id: props.event.id,
    title: props.event.title,
    status: props.event.status,
    description: props.event.description,
    order_open_at: props.event.order_open_at ?? '',
    order_close_at: props.event.order_close_at ?? '',
    expected_delivery_date: props.event.expected_delivery_date,
}));

const coverInputRef = ref<HTMLInputElement | null>(null);
const coverForm = useForm<{ cover_image: File | null }>({
    cover_image: null,
});

const onCoverChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    coverForm.cover_image = target.files?.[0] ?? null;

    if (!coverForm.cover_image) {
        return;
    }

    coverForm.post(`/admin/events/${props.event.id}/cover`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            coverForm.reset('cover_image');

            if (coverInputRef.value) {
                coverInputRef.value.value = '';
            }
        },
    });
};

const finalizeEvent = () => {
    if (props.ledger.close_blockers.length > 0) {
        setActiveTab('accounts');

        return;
    }

    if (
        !confirm(
            'এই ইভেন্টটি ফাইনালাইজ করবেন? ফলাফল fund cycle-এ যাবে এবং এরপর কোনো তথ্য (কস্ট, উইথড্র, ডিপোজিট, আয়, চার্জ, অর্ডার) আর পরিবর্তন করা যাবে না।',
        )
    ) {
        return;
    }

    router.patch(`/admin/events/${props.event.id}/finalize`, undefined, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${props.event.title} - Event Details`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader :title="props.event.title">
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="`/admin/events/${props.event.id}/orders`">
                        <ListOrdered class="size-4" />
                        Orders
                    </Link>
                </Button>
                <Button
                    v-if="!props.event.is_finalized"
                    variant="outline"
                    @click="isEditDialogOpen = true"
                >
                    <Pencil class="size-4" />
                    Edit
                </Button>
                <Button
                    v-if="!props.event.is_finalized"
                    variant="destructive"
                    @click="finalizeEvent"
                >
                    <Lock class="size-4" />
                    Finalize
                </Button>
            </template>
        </PageHeader>

        <div
            class="-mt-3 flex flex-wrap items-center gap-2 text-sm text-muted-foreground"
        >
            <StatusBadge
                :status="props.event.status"
                :label="props.event.status_label"
            />
            <StatusBadge
                :status="props.event.is_finalized ? 'finalized' : 'running'"
                :label="
                    props.event.is_finalized ? 'Finalized' : 'Not finalized'
                "
            />
            <span class="inline-flex items-center gap-1">
                <Tag class="size-3.5" />
                {{ props.event.slug }}
            </span>
            <template v-if="props.event.fund_cycle.id">
                <span>·</span>
                <Link
                    :href="`/admin/fund-cycles/${props.event.fund_cycle.id}/events`"
                    class="underline-offset-4 hover:text-foreground hover:underline"
                >
                    {{ props.event.fund_cycle.name }}
                </Link>
            </template>
        </div>

        <div class="relative overflow-hidden rounded-xl border bg-muted/50">
            <div class="aspect-21/6">
                <img
                    v-if="props.event.banner_image_url"
                    :src="props.event.banner_image_url"
                    :alt="`${props.event.title} cover`"
                    class="h-full w-full object-cover"
                />
                <div
                    v-else
                    class="flex h-full items-center justify-center text-sm text-muted-foreground"
                >
                    No cover image yet
                </div>
            </div>
            <div
                v-if="!props.event.is_finalized"
                class="absolute top-3 right-3"
            >
                <Button
                    size="sm"
                    variant="secondary"
                    :disabled="coverForm.processing"
                    @click="coverInputRef?.click()"
                >
                    <ImageUp class="size-4" />
                    {{ coverForm.processing ? 'Uploading...' : 'Update cover' }}
                </Button>
                <input
                    ref="coverInputRef"
                    type="file"
                    accept=".jpg,.jpeg,.png,.webp"
                    class="hidden"
                    @change="onCoverChange"
                />
            </div>
            <p
                v-if="coverForm.errors.cover_image"
                class="border-t bg-card px-4 py-2 text-xs text-destructive"
            >
                {{ coverForm.errors.cover_image }}
            </p>
        </div>

        <Tabs :model-value="activeTab" @update:model-value="setActiveTab">
            <TabsList class="h-auto max-w-full flex-wrap justify-start">
                <TabsTrigger
                    v-for="tab in tabs"
                    :key="tab.key"
                    :value="tab.key"
                    class="flex-none"
                >
                    {{ tab.label }}
                    <span
                        v-if="tab.count !== undefined"
                        class="text-xs text-muted-foreground tabular-nums"
                    >
                        {{ tab.count }}
                    </span>
                </TabsTrigger>
            </TabsList>

            <Card class="mt-2">
                <CardContent>
                    <TabsContent value="details">
                        <EventOverviewTab :event="props.event" />
                    </TabsContent>
                    <TabsContent value="packages">
                        <EventPackagesTab
                            :event="props.event"
                            :order-summary="props.orderSummary"
                            :status-options="props.statusOptions"
                            :package-statuses="props.packageStatuses"
                            :package-unit-types="props.packageUnitTypes"
                        />
                    </TabsContent>
                    <TabsContent value="pickup">
                        <EventPickupTab
                            :event="props.event"
                            :order-summary="props.orderSummary"
                            :status-options="props.statusOptions"
                        />
                    </TabsContent>
                    <TabsContent value="payments">
                        <EventPaymentsTab :event="props.event" />
                    </TabsContent>
                    <TabsContent value="deposits">
                        <EventBankDepositsTab :event="props.event" />
                    </TabsContent>
                    <TabsContent value="withdrawals">
                        <EventWithdrawalsTab :event="props.event" />
                    </TabsContent>
                    <TabsContent value="costs">
                        <EventCostsTab
                            :event="props.event"
                            :expense-categories="props.expenseCategories"
                        />
                    </TabsContent>
                    <TabsContent value="accounts">
                        <EventAccountsTab
                            :event="props.event"
                            :ledger="props.ledger"
                            :income-categories="props.incomeCategories"
                            @finalize="finalizeEvent"
                        />
                    </TabsContent>
                </CardContent>
            </Card>
        </Tabs>

        <FundCycleEventFormDialog
            v-model:isOpen="isEditDialogOpen"
            mode="edit"
            :fund-cycle-id="props.event.fund_cycle.id"
            :event-statuses="props.eventStatuses"
            :fund-cycle-event="editableEvent"
            :update-url="`/admin/events/${props.event.id}`"
        />
    </div>
</template>
