<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    CalendarDays,
    CreditCard,
    ChevronDown,
    Clock3,
    FileText,
    MapPin,
    Pencil,
    Tag,
    Package,
    Plus,
    Trash2,
    Wallet,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EventBankWithdrawalFormDialog from '@/components/admin/EventBankWithdrawalFormDialog.vue';
import EventExpenseFormDialog from '@/components/admin/EventExpenseFormDialog.vue';
import EventPackageFormDialog from '@/components/admin/EventPackageFormDialog.vue';
import EventPickupPointFormDialog from '@/components/admin/EventPickupPointFormDialog.vue';
import FundCycleEventFormDialog from '@/components/admin/FundCycleEventFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type EventStatusOption = {
    value: string;
    label: string;
};

type PackageStatusOption = {
    value: string;
    label: string;
};

type EventPackage = {
    id: number;
    name: string;
    description: string | null;
    unit_type: string;
    unit_type_label: string;
    unit_size: string;
    unit_label: string;
    package_price: string;
    advance_percent: string;
    min_qty_per_order: number;
    max_qty_per_order: number | null;
    stock_qty: number | null;
    sold_qty: number;
    remaining_qty: number | null;
    sort_order: number;
    status: string;
    status_label: string;
};

type EventPickupPoint = {
    id: number;
    name: string;
    area: string | null;
    address: string | null;
    contact_person: string | null;
    phone: string | null;
    sort_order: number;
    is_active: boolean;
};

type ExpenseCategoryOption = {
    value: string;
    label: string;
};

type EventExpense = {
    id: number;
    expense_date: string;
    category: string;
    category_label: string;
    amount: number;
    description: string | null;
    receipt_path: string | null;
    receipt_url: string | null;
    created_by_name: string | null;
    created_at: string | null;
};

type EventExpenseSummary = {
    total_amount: number;
    entry_count: number;
};

type EventBankWithdrawal = {
    id: number;
    withdrawal_date: string;
    amount: number;
    description: string | null;
    reference_no: string | null;
    created_by_name: string | null;
    created_at: string | null;
};

type WithdrawalSummary = {
    total_amount: number;
    entry_count: number;
};

type FloatSummary = {
    withdrawn_from_bank: number;
    logged_expenses: number;
    remaining_float: number;
    is_over_logged: boolean;
};

type CycleWithdrawalBudget = {
    allocated_amount: number;
    withdrawn_amount: number;
    remaining_amount: number;
};

type EventPaymentLog = {
    id: number;
    order_id: number;
    order_number: string | null;
    customer_name: string | null;
    amount: number;
    payment_type: string | null;
    payment_type_label: string;
    payment_method: string | null;
    payment_status: string;
    payment_status_label: string;
    transaction_reference: string | null;
    note: string | null;
    paid_at: string | null;
    verified_at: string | null;
    verified_by_name: string | null;
};

type EventPaymentSummary = {
    entry_count: number;
    verified_amount: number;
    verified_count: number;
    pending_count: number;
    failed_count: number;
};

type EventDetails = {
    id: number;
    title: string;
    slug: string;
    status: string;
    status_label: string;
    description: string | null;
    banner_image_url: string | null;
    order_open_at: string | null;
    order_close_at: string | null;
    expected_delivery_date: string | null;
    created_at: string | null;
    updated_at: string | null;
    packages: EventPackage[];
    pickup_points: EventPickupPoint[];
    expenses: EventExpense[];
    expense_summary: EventExpenseSummary;
    bank_withdrawals: EventBankWithdrawal[];
    withdrawal_summary: WithdrawalSummary;
    float_summary: FloatSummary;
    cycle_withdrawal_budget: CycleWithdrawalBudget;
    payments: EventPaymentLog[];
    payment_summary: EventPaymentSummary;
    fund_cycle: {
        id: number;
        name: string | null;
        status: string | null;
        status_label: string | null;
        start_date: string | null;
        lock_date: string | null;
        maturity_date: string | null;
        settlement_date: string | null;
    };
};

type Props = {
    event: EventDetails;
    eventStatuses: EventStatusOption[];
    packageStatuses: PackageStatusOption[];
    packageUnitTypes: PackageStatusOption[];
    expenseCategories: ExpenseCategoryOption[];
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
const isEditDialogOpen = ref(false);
const isPackageDialogOpen = ref(false);
const editingPackage = ref<EventPackage | null>(null);
const isPickupPointDialogOpen = ref(false);
const editingPickupPoint = ref<EventPickupPoint | null>(null);
const isExpenseDialogOpen = ref(false);
const editingExpense = ref<EventExpense | null>(null);
const isWithdrawalDialogOpen = ref(false);
const editingWithdrawal = ref<EventBankWithdrawal | null>(null);
const isDescriptionExpanded = ref(false);

type DetailTab =
    | 'details'
    | 'packages'
    | 'pickup'
    | 'payments'
    | 'withdrawals'
    | 'costs';

const page = usePage();
const validTabs: DetailTab[] = [
    'details',
    'packages',
    'pickup',
    'payments',
    'withdrawals',
    'costs',
];
const activeTab = ref<DetailTab>('details');

const setActiveTab = (tab: DetailTab) => {
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

        if (tab && validTabs.includes(tab as DetailTab)) {
            activeTab.value = tab as DetailTab;
        }
    },
    { immediate: true },
);

const detailTabs = computed(() => [
    { key: 'details' as const, label: 'Details' },
    {
        key: 'packages' as const,
        label: 'Packages',
        count: props.event.packages.length,
    },
    {
        key: 'pickup' as const,
        label: 'Pickup Points',
        count: props.event.pickup_points.length,
    },
    {
        key: 'payments' as const,
        label: 'Payments',
        count: props.event.payment_summary.entry_count,
    },
    {
        key: 'withdrawals' as const,
        label: 'Bank Withdrawal',
        count: props.event.withdrawal_summary.entry_count,
    },
    {
        key: 'costs' as const,
        label: 'Event Costs',
        count: props.event.expense_summary.entry_count,
    },
]);
const coverInputRef = ref<HTMLInputElement | null>(null);
const coverForm = useForm<{
    cover_image: File | null;
}>({
    cover_image: null,
});

const editableEvent = computed(() => ({
    id: props.event.id,
    title: props.event.title,
    status: props.event.status,
    description: props.event.description,
    order_open_at: props.event.order_open_at ?? '',
    order_close_at: props.event.order_close_at ?? '',
    expected_delivery_date: props.event.expected_delivery_date,
}));

const parseDateValue = (value: string | null): Date | null => {
    if (!value) {
        return null;
    }

    const parsed = new Date(value);

    return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const formatDateTime = (value: string | null): string => {
    const parsed = parseDateValue(value);

    if (!parsed) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: true,
    }).format(parsed);
};

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;

const paymentStatusVariant = (
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'verified') {
        return 'default';
    }

    if (status === 'pending') {
        return 'secondary';
    }

    if (status === 'failed') {
        return 'destructive';
    }

    return 'outline';
};

const orderShowUrl = (orderId: number): string =>
    `/admin/events/${props.event.id}/orders/${orderId}`;

const formatDate = (value: string | null): string => {
    const parsed = parseDateValue(value);

    if (!parsed) {
        return '-';
    }

    return new Intl.DateTimeFormat('en-GB', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(parsed);
};

const description = computed(
    () => props.event.description ?? 'No event description provided yet.',
);
const isLongDescription = computed(() => description.value.length > 420);
const displayedDescription = computed(() => {
    if (isDescriptionExpanded.value || !isLongDescription.value) {
        return description.value;
    }

    return `${description.value.slice(0, 420).trimEnd()}...`;
});

const onCoverChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    coverForm.cover_image = target.files?.[0] ?? null;

    if (coverForm.cover_image) {
        submitCover();
    }
};

const openCoverPicker = () => {
    if (coverForm.processing) {
        return;
    }

    coverInputRef.value?.click();
};

const submitCover = () => {
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

const openAddPackage = () => {
    activeTab.value = 'packages';
    editingPackage.value = null;
    isPackageDialogOpen.value = true;
};

const openEditPackage = (pkg: EventPackage) => {
    editingPackage.value = pkg;
    isPackageDialogOpen.value = true;
};

const deletePackage = (pkg: EventPackage) => {
    if (
        !confirm(
            `"${pkg.name}" প্যাকেজটি মুছে ফেলবেন? এটি আর নতুন অর্ডারে দেখাবে না।`,
        )
    ) {
        return;
    }

    router.delete(`/admin/events/${props.event.id}/packages/${pkg.id}`, {
        preserveScroll: true,
    });
};

const openAddPickupPoint = () => {
    activeTab.value = 'pickup';
    editingPickupPoint.value = null;
    isPickupPointDialogOpen.value = true;
};

const openEditPickupPoint = (point: EventPickupPoint) => {
    editingPickupPoint.value = point;
    isPickupPointDialogOpen.value = true;
};

const deletePickupPoint = (point: EventPickupPoint) => {
    if (!confirm(`"${point.name}" পিকআপ পয়েন্টটি মুছে ফেলবেন?`)) {
        return;
    }

    router.delete(`/admin/events/${props.event.id}/pickup-points/${point.id}`, {
        preserveScroll: true,
    });
};

const openAddWithdrawal = () => {
    activeTab.value = 'withdrawals';
    editingWithdrawal.value = null;
    isWithdrawalDialogOpen.value = true;
};

const openEditWithdrawal = (withdrawal: EventBankWithdrawal) => {
    editingWithdrawal.value = withdrawal;
    isWithdrawalDialogOpen.value = true;
};

const deleteWithdrawal = (withdrawal: EventBankWithdrawal) => {
    const label =
        withdrawal.description ||
        withdrawal.reference_no ||
        money(withdrawal.amount);

    if (!confirm(`"${label}" ব্যাংক উত্তোলন এন্ট্রি মুছে ফেলবেন?`)) {
        return;
    }

    router.delete(
        `/admin/events/${props.event.id}/bank-withdrawals/${withdrawal.id}`,
        { preserveScroll: true },
    );
};

const openAddExpense = () => {
    activeTab.value = 'costs';
    editingExpense.value = null;
    isExpenseDialogOpen.value = true;
};

const openEditExpense = (expense: EventExpense) => {
    editingExpense.value = expense;
    isExpenseDialogOpen.value = true;
};

const deleteExpense = (expense: EventExpense) => {
    const label = expense.description || expense.category_label;

    if (!confirm(`"${label}" খরচের এন্ট্রি মুছে ফেলবেন?`)) {
        return;
    }

    router.delete(`/admin/events/${props.event.id}/expenses/${expense.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="`${props.event.title} - Event Details`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="pointer-events-none absolute inset-x-0 top-0 h-24 bg-linear-to-r from-emerald-500/12 via-amber-500/10 to-cyan-500/12"
            />

            <div class="relative space-y-6">
                <div
                    class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between"
                >
                    <div class="max-w-4xl space-y-4">
                        <p
                            class="text-xs font-semibold tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Event Profile
                        </p>
                        <h1 class="text-3xl font-semibold tracking-tight">
                            {{ props.event.title }}
                        </h1>

                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <Badge class="px-2.5 py-1" variant="outline">
                                {{ props.event.status_label }}
                            </Badge>
                            <span
                                class="inline-flex items-center gap-1 rounded-full border border-sidebar-border/80 bg-muted/40 px-2.5 py-1 font-medium text-muted-foreground"
                            >
                                <Tag class="size-3.5" />
                                {{ props.event.slug }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-2 md:justify-end">
                        <Button variant="outline" as-child>
                            <Link :href="`/admin/events/${props.event.id}/orders`">
                                Order List
                            </Link>
                        </Button>
                        <Button @click="isEditDialogOpen = true">
                            <Pencil class="size-4" />
                            Edit Event
                        </Button>
                        <Button variant="outline" as-child>
                            <Link href="/admin/events">Back to Events</Link>
                        </Button>
                        <Button
                            v-if="props.event.fund_cycle.id"
                            variant="outline"
                            as-child
                        >
                            <Link
                                :href="`/admin/fund-cycles/${props.event.fund_cycle.id}/events`"
                            >
                                Cycle Event Page
                            </Link>
                        </Button>
                    </div>
                </div>

                <div
                    class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-muted/30"
                >
                    <div
                        class="relative aspect-21/8 overflow-hidden bg-muted/40"
                    >
                        <div class="absolute top-4 right-4 z-10">
                            <Button
                                size="sm"
                                variant="secondary"
                                :disabled="coverForm.processing"
                                @click="openCoverPicker"
                            >
                                {{
                                    coverForm.processing
                                        ? 'Uploading...'
                                        : 'Update Cover'
                                }}
                            </Button>
                            <input
                                ref="coverInputRef"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                class="hidden"
                                @change="onCoverChange"
                            />
                        </div>

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
                            No cover image uploaded yet
                        </div>
                    </div>
                    <p
                        v-if="coverForm.errors.cover_image"
                        class="px-4 py-3 text-xs text-destructive"
                    >
                        {{ coverForm.errors.cover_image }}
                    </p>
                </div>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-wrap gap-2 border-b border-sidebar-border/70 px-4 py-3"
            >
                <Button
                    v-for="tab in detailTabs"
                    :key="tab.key"
                    size="sm"
                    :variant="activeTab === tab.key ? 'default' : 'outline'"
                    @click="setActiveTab(tab.key)"
                >
                    {{ tab.label }}
                    <Badge
                        v-if="tab.count !== undefined"
                        variant="secondary"
                        class="ml-1.5 text-xs"
                    >
                        {{ tab.count }}
                    </Badge>
                </Button>
            </div>

            <div
                v-if="activeTab === 'details'"
                class="space-y-6 p-6"
            >
                <div class="flex items-center gap-2">
                    <FileText class="size-5 text-muted-foreground" />
                    <h2 class="text-base font-semibold">Event Details</h2>
                </div>

                <div class="grid gap-4 text-sm md:grid-cols-2">
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Order Open
                        </div>
                        <div
                            class="mt-2 flex items-center gap-2 font-medium text-foreground"
                        >
                            <Clock3 class="size-4 text-muted-foreground" />
                            {{ formatDateTime(props.event.order_open_at) }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Order Close
                        </div>
                        <div
                            class="mt-2 flex items-center gap-2 font-medium text-foreground"
                        >
                            <Clock3 class="size-4 text-muted-foreground" />
                            {{ formatDateTime(props.event.order_close_at) }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Expected Delivery
                        </div>
                        <div
                            class="mt-2 flex items-center gap-2 font-medium text-foreground"
                        >
                            <CalendarDays
                                class="size-4 text-muted-foreground"
                            />
                            {{ formatDate(props.event.expected_delivery_date) }}
                        </div>
                    </div>

                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Created At
                        </div>
                        <div
                            class="mt-2 flex items-center gap-2 font-medium text-foreground"
                        >
                            <CalendarDays
                                class="size-4 text-muted-foreground"
                            />
                            {{ props.event.created_at || '-' }}
                        </div>
                    </div>
                </div>

                <div>
                    <div class="text-xs text-muted-foreground">Description</div>
                    <p
                        class="mt-3 text-sm leading-7 whitespace-pre-line text-foreground/90"
                    >
                        {{ displayedDescription }}
                    </p>
                    <Button
                        v-if="isLongDescription"
                        variant="ghost"
                        size="sm"
                        class="mt-2 h-8 px-2 text-xs"
                        @click="isDescriptionExpanded = !isDescriptionExpanded"
                    >
                        {{
                            isDescriptionExpanded
                                ? 'Show less'
                                : 'Read full description'
                        }}
                        <ChevronDown
                            class="size-3.5 transition-transform"
                            :class="{
                                'rotate-180': isDescriptionExpanded,
                            }"
                        />
                    </Button>
                </div>

                <div class="rounded-xl border border-sidebar-border/70 p-4">
                    <div class="text-xs text-muted-foreground">
                        Cycle Context
                    </div>
                    <div class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Cycle Name
                            </p>
                            <p class="mt-1 font-medium text-foreground">
                                {{ props.event.fund_cycle.name || '-' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Cycle Status
                            </p>
                            <p class="mt-1 font-medium text-foreground">
                                {{
                                    props.event.fund_cycle.status_label ||
                                    props.event.fund_cycle.status ||
                                    '-'
                                }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Event Slug
                            </p>
                            <p
                                class="mt-1 font-medium break-all text-foreground"
                            >
                                {{ props.event.slug }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Last Updated
                            </p>
                            <p class="mt-1 font-medium text-foreground">
                                {{ props.event.updated_at || '-' }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'packages'" class="p-6">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <Package class="size-5 text-muted-foreground" />
                    <h2 class="text-base font-semibold">Packages</h2>
                    <Badge variant="secondary" class="text-xs">
                        {{ props.event.packages.length }}
                    </Badge>
                </div>
                <Button size="sm" @click="openAddPackage">
                    <Plus class="size-4" />
                    Add Package
                </Button>
            </div>

            <div
                v-if="props.event.packages.length === 0"
                class="mt-6 rounded-xl border border-dashed border-sidebar-border/70 py-10 text-center text-sm text-muted-foreground"
            >
                No packages added yet. Click "Add Package" to create one.
            </div>

            <div
                v-else
                class="mt-4 overflow-x-auto rounded-xl border border-sidebar-border/70"
            >
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-sidebar-border/70 bg-muted/30 text-xs text-muted-foreground"
                        >
                            <th class="px-4 py-3 text-left font-medium">
                                Name
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Unit
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Package Price
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Advance
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Min / Max
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Stock
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Sold
                            </th>
                            <th class="px-4 py-3 text-center font-medium">
                                Status
                            </th>
                            <th class="px-4 py-3 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="pkg in props.event.packages"
                            :key="pkg.id"
                            class="border-b border-sidebar-border/50 last:border-0 hover:bg-muted/20"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">
                                {{ pkg.name }}
                                <p
                                    v-if="pkg.description"
                                    class="mt-0.5 line-clamp-1 text-xs font-normal text-muted-foreground"
                                >
                                    {{ pkg.description }}
                                </p>
                            </td>
                            <td
                                class="px-4 py-3 text-right text-muted-foreground tabular-nums"
                            >
                                {{ pkg.unit_label }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                ৳{{ Number(pkg.package_price).toLocaleString() }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ pkg.advance_percent }}%
                            </td>
                            <td
                                class="px-4 py-3 text-right text-muted-foreground tabular-nums"
                            >
                                {{ pkg.min_qty_per_order }} /
                                {{ pkg.max_qty_per_order ?? '∞' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ pkg.stock_qty ?? '∞' }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ pkg.sold_qty }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Badge
                                    variant="outline"
                                    :class="{
                                        'border-green-500/50 text-green-600 dark:text-green-400':
                                            pkg.status === 'active',
                                        'border-yellow-500/50 text-yellow-600 dark:text-yellow-400':
                                            pkg.status === 'draft',
                                        'border-muted text-muted-foreground':
                                            pkg.status === 'inactive',
                                    }"
                                    class="text-xs"
                                >
                                    {{ pkg.status_label }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2"
                                        @click="openEditPackage(pkg)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2 text-destructive hover:text-destructive"
                                        @click="deletePackage(pkg)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>

            <div v-else-if="activeTab === 'pickup'" class="p-6">
            <div
                class="flex items-center justify-between"
            >
                <div class="flex items-center gap-2">
                    <MapPin class="size-4 text-muted-foreground" />
                    <h2 class="text-base font-semibold">Pickup Points</h2>
                    <Badge variant="secondary" class="ml-1">
                        {{ props.event.pickup_points.length }}
                    </Badge>
                </div>
                <Button size="sm" @click="openAddPickupPoint">
                    <Plus class="size-4" />
                    Add Pickup Point
                </Button>
            </div>

            <!-- Empty state -->
            <div
                v-if="props.event.pickup_points.length === 0"
                class="flex flex-col items-center justify-center gap-2 py-12 text-center text-muted-foreground"
            >
                <MapPin class="size-8 opacity-30" />
                <p class="text-sm">No pickup points added yet.</p>
                <Button size="sm" variant="outline" @click="openAddPickupPoint">
                    Add the first pickup point
                </Button>
            </div>

            <!-- Table -->
            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-sidebar-border/70 bg-muted/30 text-xs text-muted-foreground"
                        >
                            <th class="px-4 py-2 text-left font-medium">
                                Name
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Area
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Contact
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Phone
                            </th>
                            <th class="px-4 py-2 text-center font-medium">
                                Status
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="point in props.event.pickup_points"
                            :key="point.id"
                            class="hover:bg-muted/20"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ point.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ point.area ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ point.contact_person ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ point.phone ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                <Badge
                                    :variant="
                                        point.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        point.is_active ? 'Active' : 'Inactive'
                                    }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2"
                                        @click="openEditPickupPoint(point)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2 text-destructive hover:text-destructive"
                                        @click="deletePickupPoint(point)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>

            <div v-else-if="activeTab === 'payments'" class="p-6">
                <div
                    class="mb-4 rounded-xl border border-sidebar-border/70 bg-muted/30 p-4 text-sm"
                >
                    <p class="font-medium text-foreground">Payment summary</p>
                    <p class="mt-1 text-muted-foreground">
                        Total entries:
                        {{ props.event.payment_summary.entry_count }}
                        · Verified:
                        {{
                            money(
                                props.event.payment_summary.verified_amount,
                            )
                        }}
                        ({{ props.event.payment_summary.verified_count }})
                        · Pending:
                        {{ props.event.payment_summary.pending_count }}
                        · Failed:
                        {{ props.event.payment_summary.failed_count }}
                    </p>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <CreditCard class="size-4 text-muted-foreground" />
                            <h2 class="text-base font-semibold">
                                Customer Payments
                            </h2>
                            <Badge variant="secondary" class="ml-1">
                                {{
                                    props.event.payment_summary.entry_count
                                }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Advance and due payments recorded for orders on
                            this event. Verify or record new payments from the
                            order page.
                        </p>
                    </div>
                    <Button size="sm" variant="outline" class="shrink-0" as-child>
                        <Link :href="`/admin/events/${props.event.id}/orders`">
                            View Orders
                        </Link>
                    </Button>
                </div>

                <div
                    v-if="props.event.payments.length === 0"
                    class="mt-6 flex flex-col items-center justify-center gap-2 py-12 text-center text-muted-foreground"
                >
                    <CreditCard class="size-8 opacity-30" />
                    <p class="text-sm">No payments logged for this event yet.</p>
                    <Button size="sm" variant="outline" as-child>
                        <Link :href="`/admin/events/${props.event.id}/orders`">
                            Go to Orders
                        </Link>
                    </Button>
                </div>

                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/70 bg-muted/30 text-xs text-muted-foreground"
                            >
                                <th class="px-4 py-2 text-left font-medium">
                                    Date
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Amount
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Order
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Customer
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Type
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Method
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Status
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Reference
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr
                                v-for="payment in props.event.payments"
                                :key="payment.id"
                                class="hover:bg-muted/20"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{
                                        payment.paid_at ||
                                        payment.verified_at ||
                                        '—'
                                    }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ money(payment.amount) }}
                                </td>
                                <td class="px-4 py-3">
                                    <Link
                                        :href="orderShowUrl(payment.order_id)"
                                        class="font-medium text-primary hover:underline"
                                    >
                                        {{ payment.order_number || '—' }}
                                    </Link>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ payment.customer_name || '—' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ payment.payment_type_label }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ payment.payment_method || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge
                                        :variant="
                                            paymentStatusVariant(
                                                payment.payment_status,
                                            )
                                        "
                                    >
                                        {{ payment.payment_status_label }}
                                    </Badge>
                                </td>
                                <td
                                    class="max-w-xs px-4 py-3 text-muted-foreground"
                                >
                                    {{
                                        payment.transaction_reference || '—'
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else-if="activeTab === 'withdrawals'" class="p-6">
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <div class="flex items-center gap-2">
                            <Banknote class="size-4 text-muted-foreground" />
                            <h2 class="text-base font-semibold">
                                Bank Withdrawal
                            </h2>
                            <Badge variant="secondary" class="ml-1">
                                {{
                                    props.event.withdrawal_summary.entry_count
                                }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Cash taken from the joint bank account for this
                            event. Reduces Deposits → Current Balance.
                        </p>
                        <p
                            v-if="
                                props.event.withdrawal_summary.entry_count > 0
                            "
                            class="mt-2 text-sm font-medium text-foreground"
                        >
                            Total withdrawn:
                            {{
                                money(
                                    props.event.withdrawal_summary.total_amount,
                                )
                            }}
                        </p>
                    </div>
                    <Button
                        size="sm"
                        class="shrink-0"
                        :disabled="
                            props.event.cycle_withdrawal_budget
                                .remaining_amount <= 0
                        "
                        @click="openAddWithdrawal"
                    >
                        <Plus class="size-4" />
                        Record Withdrawal
                    </Button>
                </div>

                <div
                    class="mt-4 rounded-xl border border-sidebar-border/70 bg-muted/30 p-4 text-sm"
                >
                    <p class="font-medium text-foreground">
                        Fund cycle withdrawal budget
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        Member allocation (this cycle):
                        {{
                            money(
                                props.event.cycle_withdrawal_budget
                                    .allocated_amount,
                            )
                        }}
                        · Withdrawn from bank (all events in cycle):
                        {{
                            money(
                                props.event.cycle_withdrawal_budget
                                    .withdrawn_amount,
                            )
                        }}
                        · Remaining:
                        <span
                            :class="{
                                'text-destructive':
                                    props.event.cycle_withdrawal_budget
                                        .remaining_amount <= 0,
                            }"
                        >
                            {{
                                money(
                                    props.event.cycle_withdrawal_budget
                                        .remaining_amount,
                                )
                            }}
                        </span>
                    </p>
                    <p
                        v-if="
                            props.event.cycle_withdrawal_budget
                                .allocated_amount <= 0
                        "
                        class="mt-2 text-xs text-destructive"
                    >
                        Record member allocations for this fund cycle before
                        logging bank withdrawals.
                    </p>
                </div>

                <div
                    v-if="props.event.bank_withdrawals.length === 0"
                    class="mt-6 flex flex-col items-center justify-center gap-2 py-12 text-center text-muted-foreground"
                >
                    <Banknote class="size-8 opacity-30" />
                    <p class="text-sm">No bank withdrawals logged yet.</p>
                    <Button
                        size="sm"
                        variant="outline"
                        @click="openAddWithdrawal"
                    >
                        Record the first withdrawal
                    </Button>
                </div>

                <div v-else class="mt-4 overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/70 bg-muted/30 text-xs text-muted-foreground"
                            >
                                <th class="px-4 py-2 text-left font-medium">
                                    Date
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Amount
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Reference
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Description
                                </th>
                                <th class="px-4 py-2 text-left font-medium">
                                    Added By
                                </th>
                                <th class="px-4 py-2 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr
                                v-for="withdrawal in props.event
                                    .bank_withdrawals"
                                :key="withdrawal.id"
                                class="hover:bg-muted/20"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{
                                        formatDate(withdrawal.withdrawal_date)
                                    }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">
                                    {{ money(withdrawal.amount) }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ withdrawal.reference_no || '—' }}
                                </td>
                                <td
                                    class="max-w-xs px-4 py-3 text-muted-foreground"
                                >
                                    {{ withdrawal.description || '—' }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ withdrawal.created_by_name || '—' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div
                                        class="flex items-center justify-end gap-1"
                                    >
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 px-2"
                                            @click="
                                                openEditWithdrawal(withdrawal)
                                            "
                                        >
                                            <Pencil class="size-3.5" />
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            class="h-7 px-2 text-destructive hover:text-destructive"
                                            @click="
                                                deleteWithdrawal(withdrawal)
                                            "
                                        >
                                            <Trash2 class="size-3.5" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else-if="activeTab === 'costs'" class="p-6">
            <div
                class="mb-4 rounded-xl border border-sidebar-border/70 bg-muted/30 p-4 text-sm"
                :class="{
                    'border-destructive/50 bg-destructive/5':
                        props.event.float_summary.is_over_logged,
                }"
            >
                <p class="font-medium text-foreground">Event float</p>
                <p class="mt-1 text-muted-foreground">
                    Withdrawn from bank:
                    {{
                        money(props.event.float_summary.withdrawn_from_bank)
                    }}
                    · Logged expenses:
                    {{ money(props.event.float_summary.logged_expenses) }}
                    · Remaining float:
                    <span
                        :class="{
                            'text-destructive':
                                props.event.float_summary.is_over_logged,
                        }"
                    >
                        {{
                            money(props.event.float_summary.remaining_float)
                        }}
                    </span>
                </p>
                <p
                    v-if="props.event.float_summary.is_over_logged"
                    class="mt-2 text-xs text-destructive"
                >
                    Logged expenses exceed bank withdrawals for this event.
                </p>
            </div>

            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <Wallet class="size-4 text-muted-foreground" />
                        <h2 class="text-base font-semibold">Event Costs</h2>
                        <Badge variant="secondary" class="ml-1">
                            {{ props.event.expense_summary.entry_count }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Petty expenses from the event float (does not reduce
                        bank Current Balance).
                    </p>
                    <p
                        v-if="props.event.expense_summary.entry_count > 0"
                        class="mt-2 text-sm font-medium text-foreground"
                    >
                        Total:
                        {{ money(props.event.expense_summary.total_amount) }}
                        ·
                        {{ props.event.expense_summary.entry_count }}
                        {{
                            props.event.expense_summary.entry_count === 1
                                ? 'entry'
                                : 'entries'
                        }}
                    </p>
                </div>
                <Button size="sm" class="shrink-0" @click="openAddExpense">
                    <Plus class="size-4" />
                    Add Cost
                </Button>
            </div>

            <div
                v-if="props.event.expenses.length === 0"
                class="flex flex-col items-center justify-center gap-2 py-12 text-center text-muted-foreground"
            >
                <Wallet class="size-8 opacity-30" />
                <p class="text-sm">No costs logged yet.</p>
                <Button size="sm" variant="outline" @click="openAddExpense">
                    Add the first cost
                </Button>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr
                            class="border-b border-sidebar-border/70 bg-muted/30 text-xs text-muted-foreground"
                        >
                            <th class="px-4 py-2 text-left font-medium">
                                Date
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Category
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Amount
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Description
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Receipt
                            </th>
                            <th class="px-4 py-2 text-left font-medium">
                                Added By
                            </th>
                            <th class="px-4 py-2 text-right font-medium">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr
                            v-for="expense in props.event.expenses"
                            :key="expense.id"
                            class="hover:bg-muted/20"
                        >
                            <td class="px-4 py-3 font-medium">
                                {{ formatDate(expense.expense_date) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ expense.category_label }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ money(expense.amount) }}
                            </td>
                            <td
                                class="max-w-xs px-4 py-3 text-muted-foreground"
                            >
                                {{ expense.description || '—' }}
                            </td>
                            <td class="px-4 py-3">
                                <a
                                    v-if="expense.receipt_url"
                                    :href="expense.receipt_url"
                                    target="_blank"
                                    class="text-primary underline underline-offset-4"
                                >
                                    View
                                </a>
                                <span v-else class="text-muted-foreground"
                                    >—</span
                                >
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ expense.created_by_name || '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2"
                                        @click="openEditExpense(expense)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        class="h-7 px-2 text-destructive hover:text-destructive"
                                        @click="deleteExpense(expense)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            </div>
        </section>

        <FundCycleEventFormDialog
            v-model:isOpen="isEditDialogOpen"
            mode="edit"
            :fund-cycle-id="props.event.fund_cycle.id"
            :event-statuses="props.eventStatuses"
            :fund-cycle-event="editableEvent"
            :update-url="`/admin/events/${props.event.id}`"
        />

        <EventPackageFormDialog
            v-model:isOpen="isPackageDialogOpen"
            :event-id="props.event.id"
            :mode="editingPackage ? 'edit' : 'create'"
            :package-statuses="props.packageStatuses"
            :package-unit-types="props.packageUnitTypes"
            :event-package="editingPackage"
        />

        <EventPickupPointFormDialog
            v-model:isOpen="isPickupPointDialogOpen"
            :event-id="props.event.id"
            :mode="editingPickupPoint ? 'edit' : 'create'"
            :pickup-point="editingPickupPoint"
        />

        <EventBankWithdrawalFormDialog
            v-model:isOpen="isWithdrawalDialogOpen"
            :event-id="props.event.id"
            :mode="editingWithdrawal ? 'edit' : 'create'"
            :bank-withdrawal="editingWithdrawal"
            :cycle-withdrawal-budget="props.event.cycle_withdrawal_budget"
        />

        <EventExpenseFormDialog
            v-model:isOpen="isExpenseDialogOpen"
            :event-id="props.event.id"
            :mode="editingExpense ? 'edit' : 'create'"
            :expense-categories="props.expenseCategories"
            :event-expense="editingExpense"
        />
    </div>
</template>
