<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Pencil, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import FundCycleEventFormDialog from '@/components/admin/FundCycleEventFormDialog.vue';
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

type FundCycleEventPage = {
    is_settled: boolean;
    id: number;
    name: string;
    status: string;
    status_label: string;
    start_date: string | null;
    lock_date: string | null;
    maturity_date: string | null;
    settlement_date: string | null;
};

type EventStatusOption = {
    value: string;
    label: string;
};

type FundCycleEventItem = {
    id: number;
    title: string;
    slug: string;
    status: string;
    status_label: string;
    is_finalized: boolean;
    description: string | null;
    banner_image_path: string | null;
    banner_image_url: string | null;
    order_open_at: string;
    order_close_at: string;
    expected_delivery_date: string | null;
    created_at: string | null;
};

type Props = {
    fundCycle: FundCycleEventPage;
    eventStatuses: EventStatusOption[];
    events: FundCycleEventItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Fund Cycles',
                href: '/admin/fund-cycles',
            },
            {
                title: 'Events',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();
const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const selectedEvent = ref<FundCycleEventItem | null>(null);

const openEditDialog = (event: FundCycleEventItem) => {
    selectedEvent.value = event;
    isEditDialogOpen.value = true;
};

const formatDateTime = (value: string): string => {
    return value.replace('T', ' ');
};
</script>

<template>
    <Head :title="`${props.fundCycle.name} - Events`" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            :title="`${props.fundCycle.name} · Events`"
            description="Pre-order events this cycle invests in. Finalize an event to close it into the cycle result."
        >
            <template #actions>
                <Button variant="outline" as-child>
                    <Link :href="`/admin/fund-cycles/${props.fundCycle.id}`">
                        Cycle details
                    </Link>
                </Button>
                <Button
                    v-if="!props.fundCycle.is_settled"
                    @click="isCreateDialogOpen = true"
                >
                    <Plus class="size-4" />
                    Add event
                </Button>
            </template>
        </PageHeader>

        <div
            class="-mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground"
        >
            <StatusBadge
                :status="props.fundCycle.status"
                :label="props.fundCycle.status_label"
            />
            <span>Start {{ props.fundCycle.start_date || '—' }}</span>
            <span>Lock {{ props.fundCycle.lock_date || '—' }}</span>
            <span>Maturity {{ props.fundCycle.maturity_date || '—' }}</span>
            <span>Settlement {{ props.fundCycle.settlement_date || '—' }}</span>
        </div>

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="pl-4">Event</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Order window</TableHead>
                        <TableHead>Delivery</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="event in props.events" :key="event.id">
                        <TableCell class="pl-4 whitespace-normal">
                            <div class="flex items-center gap-3">
                                <img
                                    v-if="event.banner_image_url"
                                    :src="event.banner_image_url"
                                    :alt="event.title"
                                    class="h-10 w-16 shrink-0 rounded object-cover"
                                />
                                <div
                                    v-else
                                    class="h-10 w-16 shrink-0 rounded bg-muted"
                                />
                                <div class="min-w-0">
                                    <Link
                                        :href="`/admin/events/${event.id}`"
                                        class="font-medium hover:underline"
                                    >
                                        {{ event.title }}
                                    </Link>
                                    <p class="text-xs text-muted-foreground">
                                        /{{ event.slug }}
                                    </p>
                                </div>
                            </div>
                        </TableCell>
                        <TableCell>
                            <div class="flex flex-wrap gap-1">
                                <StatusBadge
                                    :status="event.status"
                                    :label="event.status_label"
                                />
                                <StatusBadge
                                    v-if="event.is_finalized"
                                    status="finalized"
                                />
                            </div>
                        </TableCell>
                        <TableCell class="text-xs text-muted-foreground">
                            <p>
                                Open {{ formatDateTime(event.order_open_at) }}
                            </p>
                            <p>
                                Close {{ formatDateTime(event.order_close_at) }}
                            </p>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ event.expected_delivery_date || '—' }}
                        </TableCell>
                        <TableCell class="pr-4">
                            <div class="flex justify-end gap-2">
                                <Button
                                    v-if="
                                        !event.is_finalized &&
                                        event.status !== 'cancelled'
                                    "
                                    variant="outline"
                                    size="sm"
                                    @click="openEditDialog(event)"
                                >
                                    <Pencil class="size-4" />
                                    Edit
                                </Button>
                                <Button size="sm" as-child>
                                    <Link :href="`/admin/events/${event.id}`">
                                        <Eye class="size-4" />
                                        Details
                                    </Link>
                                </Button>
                            </div>
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="props.events.length === 0" :colspan="5">
                        No events in this cycle yet.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

        <FundCycleEventFormDialog
            v-model:isOpen="isCreateDialogOpen"
            mode="create"
            :fund-cycle-id="props.fundCycle.id"
            :event-statuses="props.eventStatuses"
        />

        <FundCycleEventFormDialog
            v-model:isOpen="isEditDialogOpen"
            mode="edit"
            :fund-cycle-id="props.fundCycle.id"
            :event-statuses="props.eventStatuses"
            :fund-cycle-event="selectedEvent"
        />
    </div>
</template>
