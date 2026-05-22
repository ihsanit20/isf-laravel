<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Eye, Pencil, Plus } from 'lucide-vue-next';
import { ref } from 'vue';
import FundCycleEventFormDialog from '@/components/admin/FundCycleEventFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type FundCycleEventPage = {
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
    description: string | null;
    banner_image_path: string | null;
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

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div class="max-w-3xl">
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        Fund Cycle Events
                    </p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                        {{ props.fundCycle.name }}
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Events will be managed under this cycle from this
                        dedicated page.
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button @click="isCreateDialogOpen = true">
                        <Plus class="size-4" />
                        Add Event
                    </Button>
                    <Button variant="outline" as-child>
                        <Link
                            :href="`/admin/fund-cycles/${props.fundCycle.id}`"
                        >
                            Back to Details
                        </Link>
                    </Button>
                    <Button variant="outline" as-child>
                        <Link
                            :href="`/admin/fund-cycles/${props.fundCycle.id}/allocations`"
                        >
                            Allocations
                        </Link>
                    </Button>
                </div>
            </div>

            <div class="mt-6 grid gap-4 text-sm md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <div class="text-xs text-muted-foreground">Status</div>
                    <div class="mt-1">
                        <Badge variant="outline">{{
                            props.fundCycle.status_label
                        }}</Badge>
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Start</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.start_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Lock</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.lock_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Maturity</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.maturity_date || '-' }}
                    </div>
                </div>
                <div>
                    <div class="text-xs text-muted-foreground">Settlement</div>
                    <div class="mt-1 font-medium text-foreground">
                        {{ props.fundCycle.settlement_date || '-' }}
                    </div>
                </div>
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
                            <th class="px-4 py-3 font-medium">Title</th>
                            <th class="px-4 py-3 font-medium">Slug</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Order Window</th>
                            <th class="px-4 py-3 font-medium">Delivery</th>
                            <th class="px-4 py-3 font-medium">Banner</th>
                            <th class="px-4 py-3 font-medium">Created At</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="event in props.events" :key="event.id">
                            <td class="px-4 py-3 font-medium">
                                <div>{{ event.title }}</div>
                                <div
                                    v-if="event.description"
                                    class="mt-1 line-clamp-2 max-w-sm text-xs text-muted-foreground"
                                >
                                    {{ event.description }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ event.slug }}
                            </td>
                            <td class="px-4 py-3">
                                <Badge variant="outline">{{
                                    event.status_label
                                }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>
                                    Open:
                                    {{ formatDateTime(event.order_open_at) }}
                                </div>
                                <div>
                                    Close:
                                    {{ formatDateTime(event.order_close_at) }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ event.expected_delivery_date || '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ event.banner_image_path || '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ event.created_at || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        as-child
                                    >
                                        <Link
                                            :href="`/admin/events/${event.id}`"
                                        >
                                            <Eye class="size-4" />
                                            Details
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openEditDialog(event)"
                                    >
                                        <Pencil class="size-4" />
                                        Edit
                                    </Button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="props.events.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No events found for this cycle.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

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
