<script setup lang="ts">
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
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
const isDescriptionExpanded = ref(false);

type DetailTab = 'details' | 'packages' | 'pickup' | 'costs';

const page = usePage();
const validTabs: DetailTab[] = ['details', 'packages', 'pickup', 'costs'];
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

            <div v-else-if="activeTab === 'costs'" class="p-6">
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
                        Track each expense for this event (operational
                        investment).
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

        <EventExpenseFormDialog
            v-model:isOpen="isExpenseDialogOpen"
            :event-id="props.event.id"
            :mode="editingExpense ? 'edit' : 'create'"
            :expense-categories="props.expenseCategories"
            :event-expense="editingExpense"
        />
    </div>
</template>
