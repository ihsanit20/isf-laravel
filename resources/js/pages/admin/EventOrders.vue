<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, Eye, Printer } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EventOrderRecordPaymentDialog from '@/components/admin/EventOrderRecordPaymentDialog.vue';
import EventOrderStatusUpdateDialog from '@/components/admin/EventOrderStatusUpdateDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type EventSummary = {
    id: number;
    title: string;
    slug: string;
    order_open_at: string | null;
    order_close_at: string | null;
    expected_delivery_date: string | null;
};

type OrderSummary = {
    orders: {
        total: number;
        today: number;
        last_7_days: number;
        by_status: Record<string, number>;
    };
    money: {
        total_order_amount: string;
        total_advance_amount: string;
        total_due_amount: string;
        orders_with_due_count: number;
    };
    payments: {
        unpaid: number;
        pending: number;
        verified: number;
        failed: number;
        verified_amount: string;
    };
    focus: {
        verified_amount: string;
        confirmed_order_count: number;
        confirmed_order_amount: string;
        confirmed_due_amount: string;
        confirmed_orders_with_due_count: number;
        confirmed_advance_amount: string;
        confirmed_verified_payment_count: number;
        verified_payment_count: number;
        pending_order_count: number;
        delivered_order_count: number;
        cancelled_order_count: number;
    };
    pickup_points: Array<{
        id: number;
        name: string;
        order_count: number;
        by_status: Record<string, number>;
        packages: Array<{
            id: number;
            name: string;
            quantity: number;
            unit_label: string;
            pack_line_label: string;
        }>;
        total_due_amount: string;
    }>;
    packages: Array<{
        id: number;
        name: string;
        sold_qty: number;
        stock_qty: number | null;
        remaining_qty: number | null;
        order_count: number;
        by_status: Record<string, number>;
        pack_count: number;
        physical_label: string | null;
        pack_line_label: string | null;
        is_low_stock: boolean;
    }>;
};

type StatusCounts = Record<string, number>;

type PickupPointSummary = {
    name: string;
    contact_person: string | null;
};

type PackageLine = {
    line_label: string;
};

type OrderItem = {
    id: number;
    order_number: string;
    customer_name: string;
    customer_phone: string;
    status: string;
    status_label: string;
    total_amount: string;
    advance_amount: string;
    due_amount: string;
    can_record_payment: boolean;
    can_update_status: boolean;
    package_lines: PackageLine[];
    payment_status: string;
    pickup_point: PickupPointSummary | null;
    created_at: string | null;
};

type StatusOption = {
    value: string;
    label: string;
};

type PaymentMethodOption = {
    value: string;
    label: string;
};

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type PaginatedOrders = {
    data: OrderItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
    links: PaginationLink[];
};

type FilterOptions = {
    statuses: Array<{ value: string; label: string }>;
    payment_statuses: Array<{ value: string; label: string }>;
    pickup_points: Array<{ id: number; name: string }>;
};

type OrderTab = 'orders' | 'pickup' | 'packages';

type ActiveFilters = {
    tab: OrderTab;
    search: string;
    status: string;
    payment_status: string;
    pickup_point_id: string;
    from_date: string;
    to_date: string;
    has_due: boolean;
    per_page: number;
};

type Props = {
    event: EventSummary;
    summary: OrderSummary;
    orders: PaginatedOrders;
    filters: ActiveFilters;
    filterOptions: FilterOptions;
    statusOptions: StatusOption[];
    paymentMethodOptions: PaymentMethodOption[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Events',
                href: '/admin/events',
            },
            {
                title: 'Order List',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();

const isStatusDialogOpen = ref(false);
const isPaymentDialogOpen = ref(false);
const modalOrder = ref<OrderItem | null>(null);

const openStatusDialog = (order: OrderItem) => {
    modalOrder.value = order;
    isStatusDialogOpen.value = true;
};

const openPaymentDialog = (order: OrderItem) => {
    modalOrder.value = order;
    isPaymentDialogOpen.value = true;
};

const ordersIndexUrl = computed(() => `/admin/events/${props.event.id}/orders`);

const printPickupAllUrl = computed(
    () => `/admin/events/${props.event.id}/prints/pickup`,
);

const printPackageSummaryUrl = computed(
    () => `/admin/events/${props.event.id}/prints/package-summary`,
);

const printPickupHubUrl = (pickupPointId: number) =>
    `/admin/events/${props.event.id}/prints/pickup/${pickupPointId}`;

const orderTabs: Array<{ key: OrderTab; label: string }> = [
    { key: 'orders', label: 'Order List' },
    { key: 'pickup', label: 'Pickup Points' },
    { key: 'packages', label: 'Package Stock' },
];

const activeTab = computed(() => props.filters.tab);

const buildOrdersUrl = (
    overrides: Record<string, string | number | boolean> = {},
): string => {
    const params = new URLSearchParams();
    params.set('tab', 'orders');

    const values: Record<string, string | number | boolean> = {
        search: props.filters.search,
        status: props.filters.status,
        payment_status: props.filters.payment_status,
        pickup_point_id: props.filters.pickup_point_id,
        from_date: props.filters.from_date,
        to_date: props.filters.to_date,
        per_page: props.filters.per_page,
        ...overrides,
    };

    if (props.filters.has_due && overrides.has_due !== false) {
        params.set('has_due', '1');
    }

    Object.entries(values).forEach(([key, value]) => {
        if (value === '' || value === false) {
            return;
        }

        params.set(key, String(value));
    });

    return `${ordersIndexUrl.value}?${params.toString()}`;
};

const tabUrl = (tab: OrderTab): string => {
    if (tab === 'orders') {
        return buildOrdersUrl();
    }

    return `${ordersIndexUrl.value}?tab=${tab}`;
};

const filterUrl = (params: Record<string, string>): string =>
    buildOrdersUrl(params);

const money = (amount: string | number): string => {
    const value =
        typeof amount === 'string' ? Number.parseFloat(amount) : amount;

    return `${Number.isFinite(value) ? value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00'} BDT`;
};

const lowStockPackages = computed(() =>
    props.summary.packages.filter((pkg) => pkg.is_low_stock),
);

const statusColumns = computed(() => props.filterOptions.statuses);

const statusBreakdownColumns = computed(() =>
    statusColumns.value.filter((status) => status.value !== 'confirmed'),
);

const statusCount = (counts: StatusCounts, status: string): number =>
    counts[status] ?? 0;

const hasActiveFilters = computed(() => {
    const f = props.filters;

    return !!(
        f.search ||
        f.status ||
        f.payment_status ||
        f.pickup_point_id ||
        f.from_date ||
        f.to_date ||
        f.has_due
    );
});

const emptyMessage = computed(() => {
    if (props.orders.data.length > 0) {
        return '';
    }

    return hasActiveFilters.value
        ? 'কোনো order মিলেনি। ফিল্টার পরিবর্তন করে আবার চেষ্টা করুন।'
        : 'এই event-এ এখনো কোন order নেই।';
});

const paginationLabel = (label: string): string =>
    label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace(/<\/?[^>]+(>|$)/g, '');
</script>

<template>
    <Head :title="`${props.event.title} - Order List`" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ props.event.title }} - Orders
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Focus on confirmed orders and verified payments. Pending
                        and other counts are shown for reference.
                    </p>
                    <dl
                        class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                    >
                        <div v-if="props.event.order_open_at">
                            <dt class="inline font-medium text-foreground">
                                Orders open:
                            </dt>
                            <dd class="inline">
                                {{ props.event.order_open_at }}
                            </dd>
                        </div>
                        <div v-if="props.event.order_close_at">
                            <dt class="inline font-medium text-foreground">
                                Orders close:
                            </dt>
                            <dd class="inline">
                                {{ props.event.order_close_at }}
                            </dd>
                        </div>
                        <div v-if="props.event.expected_delivery_date">
                            <dt class="inline font-medium text-foreground">
                                Expected delivery:
                            </dt>
                            <dd class="inline">
                                {{ props.event.expected_delivery_date }}
                            </dd>
                        </div>
                    </dl>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" as-child>
                        <Link :href="`/admin/events/${props.event.id}`">
                            Event Details
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link href="/admin/events">
                            <ArrowLeft class="size-4" />
                            Back to Events
                        </Link>
                    </Button>
                </div>
            </div>
        </section>

        <section
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5"
        >
            <div
                class="rounded-xl border-2 border-primary/30 bg-primary/5 p-6 shadow-sm"
            >
                <p
                    class="text-xs font-medium tracking-wide text-primary uppercase"
                >
                    Verified collected
                </p>
                <p
                    class="mt-3 text-3xl font-bold tracking-tight text-foreground"
                >
                    {{ money(props.summary.focus.verified_amount) }}
                </p>
                <p class="mt-3 text-sm text-muted-foreground">
                    Verified payment records for this event
                </p>
            </div>
            <Link
                :href="filterUrl({ status: 'confirmed' })"
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm transition-colors hover:bg-muted/30 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">
                    Confirmed order total
                </p>
                <p class="mt-3 text-3xl font-semibold text-foreground">
                    {{ money(props.summary.focus.confirmed_order_amount) }}
                </p>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{
                        props.summary.focus.confirmed_order_count.toLocaleString()
                    }}
                    confirmed orders
                </p>
            </Link>
            <Link
                :href="filterUrl({ status: 'confirmed', has_due: '1' })"
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm transition-colors hover:bg-muted/30 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">Confirmed due</p>
                <p class="mt-3 text-3xl font-semibold text-amber-600">
                    {{ money(props.summary.focus.confirmed_due_amount) }}
                </p>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{
                        props.summary.focus.confirmed_orders_with_due_count.toLocaleString()
                    }}
                    confirmed orders with balance due
                </p>
            </Link>
            <Link
                :href="filterUrl({ status: 'confirmed' })"
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm transition-colors hover:bg-muted/30 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">Confirmed orders</p>
                <p class="mt-3 text-3xl font-semibold text-foreground">
                    {{
                        props.summary.focus.confirmed_order_count.toLocaleString()
                    }}
                </p>
                <p class="mt-3 text-sm text-muted-foreground">
                    {{
                        props.summary.focus.confirmed_verified_payment_count.toLocaleString()
                    }}
                    with verified payment
                </p>
            </Link>
            <Link
                :href="filterUrl({ payment_status: 'verified' })"
                class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm transition-colors hover:bg-muted/30 dark:border-sidebar-border"
            >
                <p class="text-xs text-muted-foreground">Verified payments</p>
                <p class="mt-3 text-3xl font-semibold text-foreground">
                    {{
                        props.summary.focus.verified_payment_count.toLocaleString()
                    }}
                </p>
                <p class="mt-3 text-sm text-muted-foreground">
                    Latest payment status is verified
                </p>
            </Link>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-muted/20 px-4 py-3 text-sm text-muted-foreground dark:border-sidebar-border"
        >
            <span class="font-medium text-foreground">Also:</span>
            <Link
                :href="filterUrl({ status: 'pending' })"
                class="ml-2 underline-offset-4 hover:underline"
            >
                Pending {{ props.summary.focus.pending_order_count }}
            </Link>
            <span class="mx-2">·</span>
            <Link
                :href="filterUrl({ status: 'delivered' })"
                class="underline-offset-4 hover:underline"
            >
                Delivered {{ props.summary.focus.delivered_order_count }}
            </Link>
            <span class="mx-2">·</span>
            <span
                >Cancelled {{ props.summary.focus.cancelled_order_count }}</span
            >
            <span class="mx-2">·</span>
            <Link
                :href="filterUrl({ payment_status: 'unpaid' })"
                class="underline-offset-4 hover:underline"
            >
                Unpaid {{ props.summary.payments.unpaid }}
            </Link>
            <span class="mx-2">·</span>
            <Link
                :href="filterUrl({ payment_status: 'pending' })"
                class="underline-offset-4 hover:underline"
            >
                Payment pending {{ props.summary.payments.pending }}
            </Link>
            <span class="mx-2">·</span>
            <Link
                :href="filterUrl({ has_due: '1' })"
                class="underline-offset-4 hover:underline"
            >
                All due {{ money(props.summary.money.total_due_amount) }}
            </Link>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-wrap gap-2 border-b border-sidebar-border/70 p-4"
            >
                <Button
                    v-for="tab in orderTabs"
                    :key="tab.key"
                    size="sm"
                    :variant="activeTab === tab.key ? 'default' : 'outline'"
                    as-child
                >
                    <Link :href="tabUrl(tab.key)">{{ tab.label }}</Link>
                </Button>
            </div>

            <div v-if="activeTab === 'orders'" class="space-y-4 p-4">
                <form
                    method="get"
                    :action="ordersIndexUrl"
                    class="grid gap-3 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
                >
                    <input type="hidden" name="tab" value="orders" />
                    <input
                        name="search"
                        type="text"
                        :value="props.filters.search"
                        placeholder="Search order, name, phone"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    />
                    <select
                        name="status"
                        :value="props.filters.status"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All status</option>
                        <option
                            v-for="status in props.filterOptions.statuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                    <select
                        name="payment_status"
                        :value="props.filters.payment_status"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All payment</option>
                        <option
                            v-for="payment in props.filterOptions
                                .payment_statuses"
                            :key="payment.value"
                            :value="payment.value"
                        >
                            {{ payment.label }}
                        </option>
                    </select>
                    <select
                        name="pickup_point_id"
                        :value="props.filters.pickup_point_id"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option value="">All pickup points</option>
                        <option
                            v-for="point in props.filterOptions.pickup_points"
                            :key="point.id"
                            :value="point.id"
                        >
                            {{ point.name }}
                        </option>
                    </select>
                    <input
                        name="from_date"
                        type="date"
                        :value="props.filters.from_date"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    />
                    <input
                        name="to_date"
                        type="date"
                        :value="props.filters.to_date"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    />
                    <label
                        class="flex h-9 items-center gap-2 rounded-md border border-input px-3 text-sm"
                    >
                        <input
                            name="has_due"
                            type="checkbox"
                            value="1"
                            :checked="props.filters.has_due"
                            class="size-4 rounded border-input"
                        />
                        Has due only
                    </label>
                    <select
                        name="per_page"
                        :value="props.filters.per_page"
                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm"
                    >
                        <option :value="15">15 per page</option>
                        <option :value="25">25 per page</option>
                        <option :value="50">50 per page</option>
                        <option :value="100">100 per page</option>
                    </select>
                    <div class="flex gap-2 xl:col-span-2">
                        <Button type="submit" size="sm" class="h-9">
                            Filter
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-9"
                            as-child
                        >
                            <Link :href="tabUrl('orders')">Reset</Link>
                        </Button>
                    </div>
                </form>

                <div
                    class="overflow-x-auto rounded-lg border border-sidebar-border/70"
                >
                    <table
                        class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                    >
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-4 py-3 font-medium">Order ID</th>
                                <th class="px-4 py-3 font-medium">Customer</th>
                                <th class="px-4 py-3 font-medium">Phone</th>
                                <th class="px-4 py-3 font-medium">
                                    Pickup Point
                                </th>
                                <th class="px-4 py-3 font-medium">Packages</th>
                                <th class="px-4 py-3 font-medium">Total</th>
                                <th class="px-4 py-3 font-medium">Advance</th>
                                <th class="px-4 py-3 font-medium">Due</th>
                                <th class="px-4 py-3 font-medium">Payment</th>
                                <th class="px-4 py-3 font-medium">Status</th>
                                <th class="px-4 py-3 font-medium">
                                    Created At
                                </th>
                                <th class="px-4 py-3 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr
                                v-for="order in props.orders.data"
                                :key="order.id"
                            >
                                <td class="px-4 py-3 font-medium">
                                    {{ order.order_number }}
                                </td>
                                <td class="px-4 py-3">
                                    {{ order.customer_name }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ order.customer_phone }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    <template v-if="order.pickup_point">
                                        <div>{{ order.pickup_point.name }}</div>
                                        <div
                                            v-if="
                                                order.pickup_point
                                                    .contact_person
                                            "
                                            class="text-xs"
                                        >
                                            {{
                                                order.pickup_point
                                                    .contact_person
                                            }}
                                        </div>
                                    </template>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    <p
                                        v-if="order.package_lines.length"
                                        class="m-0 leading-none tracking-tight"
                                    >
                                        <span
                                            v-for="(
                                                line, index
                                            ) in order.package_lines"
                                            :key="index"
                                            class="block whitespace-nowrap"
                                            >{{ line.line_label }}</span
                                        >
                                    </p>
                                    <span v-else>-</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ order.total_amount }}
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ order.advance_amount }}
                                </td>
                                <td class="px-4 py-3">
                                    <Button
                                        v-if="order.can_record_payment"
                                        variant="link"
                                        class="h-auto p-0 font-semibold text-amber-600 hover:text-amber-700"
                                        :title="`Record payment for ${order.order_number}`"
                                        @click="openPaymentDialog(order)"
                                    >
                                        {{ order.due_amount }}
                                    </Button>
                                    <span
                                        v-else
                                        class="font-semibold text-amber-600"
                                    >
                                        {{ order.due_amount }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ order.payment_status }}
                                </td>
                                <td class="px-4 py-3">
                                    <button
                                        v-if="order.can_update_status"
                                        type="button"
                                        class="rounded-full focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :title="`Update status for ${order.order_number}`"
                                        @click="openStatusDialog(order)"
                                    >
                                        <Badge
                                            variant="outline"
                                            class="cursor-pointer transition-colors hover:bg-muted"
                                        >
                                            {{ order.status_label }}
                                        </Badge>
                                    </button>
                                    <Badge v-else variant="outline">
                                        {{ order.status_label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    {{ order.created_at || '-' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="`/admin/events/${props.event.id}/orders/${order.id}`"
                                        >
                                            <Eye class="size-4" />
                                            Details
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                            <tr v-if="props.orders.data.length === 0">
                                <td
                                    colspan="12"
                                    class="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {{ emptyMessage }}
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        class="flex flex-col gap-3 border-t border-sidebar-border/70 px-4 py-3 text-sm md:flex-row md:items-center md:justify-between"
                    >
                        <p class="text-muted-foreground">
                            Showing {{ props.orders.from || 0 }} to
                            {{ props.orders.to || 0 }} of
                            {{ props.orders.total.toLocaleString() }} orders
                        </p>
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                v-for="link in props.orders.links"
                                :key="link.label"
                                :href="link.url || ''"
                                :class="[
                                    'rounded-md border px-3 py-1.5 text-xs',
                                    link.active
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'border-sidebar-border/70 text-muted-foreground',
                                    !link.url
                                        ? 'pointer-events-none opacity-50'
                                        : '',
                                ]"
                            >
                                {{ paginationLabel(link.label) }}
                            </Link>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="activeTab === 'pickup'" class="p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold tracking-tight">
                            Orders by Pickup Point
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Status counts show all orders; Total, packages, and
                            due reflect confirmed orders (operational focus).
                        </p>
                    </div>
                    <Button variant="outline" size="sm" as-child>
                        <a
                            :href="printPickupAllUrl"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <Printer class="size-4" />
                            সব হাব প্রিন্ট
                        </a>
                    </Button>
                </div>
                <div
                    v-if="props.summary.pickup_points.length === 0"
                    class="mt-6 text-center text-sm text-muted-foreground"
                >
                    No pickup points configured for this event.
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                    >
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-3 py-2 font-medium">
                                    Pickup Point
                                </th>
                                <th class="px-3 py-2 font-medium">Packages</th>
                                <th class="px-3 py-2 font-medium">Confirmed</th>
                                <th
                                    v-for="status in statusBreakdownColumns"
                                    :key="status.value"
                                    class="px-3 py-2 font-medium"
                                >
                                    {{ status.label }}
                                </th>
                                <th class="px-3 py-2 font-medium">Total Due</th>
                                <th class="px-3 py-2 font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr
                                v-for="point in props.summary.pickup_points"
                                :key="point.id"
                            >
                                <td class="px-3 py-2 font-medium">
                                    {{ point.name }}
                                </td>
                                <td
                                    class="px-3 py-2 align-top text-muted-foreground"
                                >
                                    <ul
                                        v-if="point.packages.length > 0"
                                        class="m-0 list-none space-y-1 p-0 text-xs"
                                    >
                                        <li
                                            v-for="pkg in point.packages"
                                            :key="pkg.id"
                                        >
                                            <span
                                                class="font-medium text-foreground"
                                            >
                                                {{ pkg.name }}
                                            </span>
                                            <span class="text-muted-foreground">
                                                · {{ pkg.pack_line_label }}
                                            </span>
                                        </li>
                                    </ul>
                                    <span v-else class="text-xs">—</span>
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    {{ point.order_count.toLocaleString() }}
                                </td>
                                <td
                                    v-for="status in statusBreakdownColumns"
                                    :key="`${point.id}-${status.value}`"
                                    class="px-3 py-2"
                                >
                                    <Link
                                        v-if="
                                            statusCount(
                                                point.by_status,
                                                status.value,
                                            ) > 0
                                        "
                                        :href="
                                            filterUrl({
                                                pickup_point_id: String(
                                                    point.id,
                                                ),
                                                status: status.value,
                                            })
                                        "
                                        class="text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                    >
                                        {{
                                            statusCount(
                                                point.by_status,
                                                status.value,
                                            ).toLocaleString()
                                        }}
                                    </Link>
                                    <span v-else class="text-muted-foreground">
                                        0
                                    </span>
                                </td>
                                <td
                                    class="px-3 py-2 font-medium text-amber-600"
                                >
                                    {{ money(point.total_due_amount) }}
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-wrap gap-2">
                                        <Link
                                            :href="
                                                filterUrl({
                                                    pickup_point_id: String(
                                                        point.id,
                                                    ),
                                                })
                                            "
                                            class="text-xs font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            View all
                                        </Link>
                                        <a
                                            :href="printPickupHubUrl(point.id)"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1 text-xs font-medium text-primary underline-offset-4 hover:underline"
                                        >
                                            <Printer class="size-3.5" />
                                            প্রিন্ট
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div v-else-if="activeTab === 'packages'" class="p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-sm font-semibold tracking-tight">
                            Package Stock Snapshot
                        </h2>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Status counts show all orders; Confirmed and ordered
                            totals reflect confirmed only (operational focus).
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button variant="outline" size="sm" as-child>
                            <a
                                :href="printPackageSummaryUrl"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <Printer class="size-4" />
                                প্যাকিং লিস্ট
                            </a>
                        </Button>
                        <Link
                            :href="`/admin/events/${props.event.id}`"
                            class="text-xs font-medium text-primary underline-offset-4 hover:underline"
                        >
                            Manage packages
                        </Link>
                    </div>
                </div>
                <div
                    v-if="props.summary.packages.length === 0"
                    class="mt-6 text-center text-sm text-muted-foreground"
                >
                    No packages configured for this event.
                </div>
                <div v-else class="mt-4 overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                    >
                        <thead class="bg-muted/40 text-left">
                            <tr>
                                <th class="px-3 py-2 font-medium">Package</th>
                                <th class="px-3 py-2 font-medium">Ordered</th>
                                <th class="px-3 py-2 font-medium">Stock</th>
                                <th class="px-3 py-2 font-medium">Confirmed</th>
                                <th
                                    v-for="status in statusBreakdownColumns"
                                    :key="status.value"
                                    class="px-3 py-2 font-medium"
                                >
                                    {{ status.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-sidebar-border/70">
                            <tr
                                v-for="pkg in props.summary.packages"
                                :key="pkg.id"
                                :class="
                                    pkg.is_low_stock ? 'bg-amber-500/5' : ''
                                "
                            >
                                <td class="px-3 py-2 font-medium">
                                    {{ pkg.name }}
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    <template v-if="pkg.pack_count > 0">
                                        <div class="text-xs">
                                            {{ pkg.pack_line_label }}
                                        </div>
                                    </template>
                                    <span v-else>—</span>
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    Sold: {{ pkg.sold_qty }}
                                    <template v-if="pkg.stock_qty !== null">
                                        · Left:
                                        {{ pkg.remaining_qty ?? 0 }} /
                                        {{ pkg.stock_qty }}
                                    </template>
                                    <template v-else> · No cap</template>
                                </td>
                                <td class="px-3 py-2 text-muted-foreground">
                                    {{ pkg.order_count.toLocaleString() }}
                                </td>
                                <td
                                    v-for="status in statusBreakdownColumns"
                                    :key="`${pkg.id}-${status.value}`"
                                    class="px-3 py-2 text-muted-foreground"
                                >
                                    {{
                                        statusCount(
                                            pkg.by_status,
                                            status.value,
                                        ).toLocaleString()
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-if="lowStockPackages.length > 0"
                    class="mt-3 text-xs text-amber-600"
                >
                    Low stock (≤5 remaining):
                    {{ lowStockPackages.map((pkg) => pkg.name).join(', ') }}
                </p>
            </div>
        </section>

        <EventOrderStatusUpdateDialog
            v-model:is-open="isStatusDialogOpen"
            :event-id="props.event.id"
            :order="modalOrder"
            :status-options="props.statusOptions"
        />
        <EventOrderRecordPaymentDialog
            v-model:is-open="isPaymentDialogOpen"
            :event-id="props.event.id"
            :order="modalOrder"
            :payment-method-options="props.paymentMethodOptions"
        />
    </div>
</template>
