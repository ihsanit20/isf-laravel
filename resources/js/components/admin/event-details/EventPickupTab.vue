<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { MapPin, Pencil, Plus, Printer, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import {
    isEventLocked,
    statusCount,
} from '@/components/admin/event-details/types';
import type {
    EventDetails,
    EventPickupPoint,
    Option,
    OrderSummary,
} from '@/components/admin/event-details/types';
import EventPickupPointFormDialog from '@/components/admin/EventPickupPointFormDialog.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney } from '@/lib/format';

const props = defineProps<{
    event: EventDetails;
    orderSummary: OrderSummary;
    statusOptions: Option[];
}>();

const isDialogOpen = ref(false);
const editingPoint = ref<EventPickupPoint | null>(null);

const statusColumns = computed(() =>
    props.statusOptions.filter((status) => status.value !== 'confirmed'),
);

const printAllUrl = computed(
    () => `/admin/events/${props.event.id}/prints/pickup`,
);

const printHubUrl = (pickupPointId: number) =>
    `/admin/events/${props.event.id}/prints/pickup/${pickupPointId}`;

const ordersUrl = (params: Record<string, string>): string =>
    `/admin/events/${props.event.id}/orders?${new URLSearchParams(params).toString()}`;

const openDialog = (point: EventPickupPoint | null = null) => {
    editingPoint.value = point;
    isDialogOpen.value = true;
};

const deletePoint = (point: EventPickupPoint) => {
    if (!confirm(`"${point.name}" পিকআপ পয়েন্টটি মুছে ফেলবেন?`)) {
        return;
    }

    router.delete(`/admin/events/${props.event.id}/pickup-points/${point.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="grid gap-8">
        <section class="grid gap-3">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-semibold">Pickup points</h3>
                <Button
                    v-if="!isEventLocked(props.event)"
                    size="sm"
                    @click="openDialog()"
                >
                    <Plus class="size-4" />
                    Add pickup point
                </Button>
            </div>

            <EmptyState
                v-if="props.event.pickup_points.length === 0"
                :icon="MapPin"
                title="No pickup points yet"
                description="Customers choose a pickup point when they order."
            />

            <div v-else class="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12 pl-4">SL</TableHead>
                            <TableHead>Name</TableHead>
                            <TableHead>Area</TableHead>
                            <TableHead>Contact</TableHead>
                            <TableHead>Phone</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="pr-4 text-right" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(point, index) in props.event.pickup_points"
                            :key="point.id"
                        >
                            <TableCell
                                class="pl-4 text-muted-foreground tabular-nums"
                                >{{ index + 1 }}</TableCell
                            >
                            <TableCell class="font-medium">
                                {{ point.name }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ point.area ?? '—' }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ point.contact_person ?? '—' }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                {{ point.phone ?? '—' }}
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :status="
                                        point.is_active ? 'active' : 'inactive'
                                    "
                                />
                            </TableCell>
                            <TableCell class="pr-4">
                                <div
                                    v-if="!isEventLocked(props.event)"
                                    class="flex justify-end gap-1"
                                >
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        @click="openDialog(point)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-destructive hover:text-destructive"
                                        @click="deletePoint(point)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </section>

        <section class="grid gap-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <h3 class="font-semibold">Orders by pickup point</h3>
                    <p class="text-sm text-muted-foreground">
                        Status counts show all orders; totals, packages and due
                        reflect confirmed orders only.
                    </p>
                </div>
                <Button variant="outline" size="sm" as-child>
                    <a
                        :href="printAllUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Printer class="size-4" />
                        সব হাব প্রিন্ট
                    </a>
                </Button>
            </div>

            <p
                v-if="props.orderSummary.pickup_points.length === 0"
                class="text-sm text-muted-foreground"
            >
                No pickup points configured for this event.
            </p>

            <div v-else class="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="w-12 pl-4">SL</TableHead>
                            <TableHead>Pickup point</TableHead>
                            <TableHead>Packages</TableHead>
                            <TableHead class="text-right">Confirmed</TableHead>
                            <TableHead
                                v-for="status in statusColumns"
                                :key="status.value"
                                class="text-right"
                            >
                                {{ status.label }}
                            </TableHead>
                            <TableHead class="text-right">Total due</TableHead>
                            <TableHead class="pr-4 text-right" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="(point, index) in props.orderSummary
                                .pickup_points"
                            :key="point.id"
                        >
                            <TableCell
                                class="pl-4 text-muted-foreground tabular-nums"
                                >{{ index + 1 }}</TableCell
                            >
                            <TableCell class="font-medium">
                                {{ point.name }}
                            </TableCell>
                            <TableCell class="whitespace-normal">
                                <ul
                                    v-if="point.packages.length > 0"
                                    class="grid gap-1 text-xs"
                                >
                                    <li
                                        v-for="pkg in point.packages"
                                        :key="pkg.id"
                                    >
                                        <span class="font-medium">
                                            {{ pkg.name }}
                                        </span>
                                        <span class="text-muted-foreground">
                                            · {{ pkg.pack_line_label }}
                                        </span>
                                    </li>
                                </ul>
                                <span v-else class="text-xs">—</span>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ point.order_count.toLocaleString() }}
                            </TableCell>
                            <TableCell
                                v-for="status in statusColumns"
                                :key="`${point.id}-${status.value}`"
                                class="text-right tabular-nums"
                            >
                                <Link
                                    v-if="
                                        statusCount(
                                            point.by_status,
                                            status.value,
                                        ) > 0
                                    "
                                    :href="
                                        ordersUrl({
                                            pickup_point_id: String(point.id),
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
                            </TableCell>
                            <TableCell
                                class="text-right font-medium text-amber-600 tabular-nums dark:text-amber-400"
                            >
                                {{
                                    formatMoney(Number(point.total_due_amount))
                                }}
                            </TableCell>
                            <TableCell class="pr-4">
                                <div class="flex justify-end gap-3 text-xs">
                                    <Link
                                        :href="
                                            ordersUrl({
                                                pickup_point_id: String(
                                                    point.id,
                                                ),
                                            })
                                        "
                                        class="font-medium underline-offset-4 hover:underline"
                                    >
                                        View all
                                    </Link>
                                    <a
                                        :href="printHubUrl(point.id)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 font-medium underline-offset-4 hover:underline"
                                    >
                                        <Printer class="size-3.5" />
                                        প্রিন্ট
                                    </a>
                                </div>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </section>

        <EventPickupPointFormDialog
            v-model:isOpen="isDialogOpen"
            :event-id="props.event.id"
            :mode="editingPoint ? 'edit' : 'create'"
            :pickup-point="editingPoint"
        />
    </div>
</template>
