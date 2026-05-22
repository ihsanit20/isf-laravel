<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    CalendarDays,
    ChevronDown,
    Clock3,
    Pencil,
    Tag,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FundCycleEventFormDialog from '@/components/admin/FundCycleEventFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type EventStatusOption = {
    value: string;
    label: string;
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
const isDescriptionExpanded = ref(false);
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

                <div class="">
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
        </section>

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
