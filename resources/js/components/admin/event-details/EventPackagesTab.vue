<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Package, Pencil, Plus, Printer, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { statusCount } from '@/components/admin/event-details/types';
import type {
    EventDetails,
    EventPackage,
    Option,
    OrderSummary,
} from '@/components/admin/event-details/types';
import EventPackageFormDialog from '@/components/admin/EventPackageFormDialog.vue';
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
    packageStatuses: Option[];
    packageUnitTypes: Option[];
}>();

const isDialogOpen = ref(false);
const editingPackage = ref<EventPackage | null>(null);

const statusColumns = computed(() =>
    props.statusOptions.filter((status) => status.value !== 'confirmed'),
);

const lowStockPackages = computed(() =>
    props.orderSummary.packages.filter((pkg) => pkg.is_low_stock),
);

const printUrl = computed(
    () => `/admin/events/${props.event.id}/prints/package-summary`,
);

const openDialog = (pkg: EventPackage | null = null) => {
    editingPackage.value = pkg;
    isDialogOpen.value = true;
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
</script>

<template>
    <div class="grid gap-8">
        <section class="grid gap-3">
            <div class="flex items-center justify-between gap-2">
                <h3 class="font-semibold">Packages</h3>
                <Button
                    v-if="!props.event.is_finalized"
                    size="sm"
                    @click="openDialog()"
                >
                    <Plus class="size-4" />
                    Add package
                </Button>
            </div>

            <EmptyState
                v-if="props.event.packages.length === 0"
                :icon="Package"
                title="No packages yet"
                description="Add a package to start taking orders."
            />

            <div v-else class="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="pl-4">Name</TableHead>
                            <TableHead class="text-right">Unit</TableHead>
                            <TableHead class="text-right">Price</TableHead>
                            <TableHead class="text-right">Advance</TableHead>
                            <TableHead class="text-right">Min / Max</TableHead>
                            <TableHead class="text-right">Stock</TableHead>
                            <TableHead class="text-right">Sold</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="pr-4 text-right" />
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="pkg in props.event.packages"
                            :key="pkg.id"
                        >
                            <TableCell class="max-w-xs pl-4 whitespace-normal">
                                <p class="font-medium">{{ pkg.name }}</p>
                                <p
                                    v-if="pkg.description"
                                    class="line-clamp-1 text-xs text-muted-foreground"
                                >
                                    {{ pkg.description }}
                                </p>
                            </TableCell>
                            <TableCell
                                class="text-right text-muted-foreground tabular-nums"
                            >
                                {{ pkg.unit_label }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ formatMoney(Number(pkg.package_price)) }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ pkg.advance_percent }}%
                            </TableCell>
                            <TableCell
                                class="text-right text-muted-foreground tabular-nums"
                            >
                                {{ pkg.min_qty_per_order }} /
                                {{ pkg.max_qty_per_order ?? '∞' }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ pkg.stock_qty ?? '∞' }}
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ pkg.sold_qty }}
                            </TableCell>
                            <TableCell>
                                <StatusBadge
                                    :status="pkg.status"
                                    :label="pkg.status_label"
                                />
                            </TableCell>
                            <TableCell class="pr-4">
                                <div
                                    v-if="!props.event.is_finalized"
                                    class="flex justify-end gap-1"
                                >
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8"
                                        @click="openDialog(pkg)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        class="size-8 text-destructive hover:text-destructive"
                                        @click="deletePackage(pkg)"
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
                    <h3 class="font-semibold">Stock snapshot</h3>
                    <p class="text-sm text-muted-foreground">
                        Status counts show all orders; confirmed and ordered
                        totals reflect confirmed orders only.
                    </p>
                </div>
                <Button variant="outline" size="sm" as-child>
                    <a
                        :href="printUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <Printer class="size-4" />
                        প্যাকিং লিস্ট
                    </a>
                </Button>
            </div>

            <p
                v-if="props.orderSummary.packages.length === 0"
                class="text-sm text-muted-foreground"
            >
                No packages configured for this event.
            </p>

            <div v-else class="rounded-xl border">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead class="pl-4">Package</TableHead>
                            <TableHead>Ordered</TableHead>
                            <TableHead>Stock</TableHead>
                            <TableHead class="text-right">Confirmed</TableHead>
                            <TableHead
                                v-for="status in statusColumns"
                                :key="status.value"
                                class="text-right last:pr-4"
                            >
                                {{ status.label }}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="pkg in props.orderSummary.packages"
                            :key="pkg.id"
                            :class="pkg.is_low_stock ? 'bg-amber-500/5' : ''"
                        >
                            <TableCell class="pl-4 font-medium">
                                {{ pkg.name }}
                            </TableCell>
                            <TableCell class="text-xs text-muted-foreground">
                                {{
                                    pkg.pack_count > 0
                                        ? pkg.pack_line_label
                                        : '—'
                                }}
                            </TableCell>
                            <TableCell class="text-muted-foreground">
                                Sold {{ pkg.sold_qty }}
                                <template v-if="pkg.stock_qty !== null">
                                    · Left {{ pkg.remaining_qty ?? 0 }} /
                                    {{ pkg.stock_qty }}
                                </template>
                                <template v-else> · No cap</template>
                            </TableCell>
                            <TableCell class="text-right tabular-nums">
                                {{ pkg.order_count.toLocaleString() }}
                            </TableCell>
                            <TableCell
                                v-for="status in statusColumns"
                                :key="`${pkg.id}-${status.value}`"
                                class="text-right text-muted-foreground tabular-nums last:pr-4"
                            >
                                {{
                                    statusCount(
                                        pkg.by_status,
                                        status.value,
                                    ).toLocaleString()
                                }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <p
                v-if="lowStockPackages.length > 0"
                class="text-xs text-amber-600 dark:text-amber-400"
            >
                Low stock (≤5 remaining):
                {{ lowStockPackages.map((pkg) => pkg.name).join(', ') }}
            </p>
        </section>

        <EventPackageFormDialog
            v-model:isOpen="isDialogOpen"
            :event-id="props.event.id"
            :mode="editingPackage ? 'edit' : 'create'"
            :package-statuses="props.packageStatuses"
            :package-unit-types="props.packageUnitTypes"
            :event-package="editingPackage"
        />
    </div>
</template>
