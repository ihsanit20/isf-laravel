<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Pencil } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import FundCycleEventFormDialog from '@/components/admin/FundCycleEventFormDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '-';
    }

    return value.replace('T', ' ');
};

const onCoverChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    coverForm.cover_image = target.files?.[0] ?? null;
};

const submitCover = () => {
    coverForm.post(`/admin/events/${props.event.id}/cover`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            coverForm.reset('cover_image');
        },
    });
};
</script>

<template>
    <Head :title="`${props.event.title} - Event Details`" />

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
                        Event Details
                    </p>
                    <h1 class="mt-2 text-2xl font-semibold tracking-tight">
                        {{ props.event.title }}
                    </h1>
                    <p
                        v-if="props.event.description"
                        class="mt-2 text-sm text-muted-foreground"
                    >
                        {{ props.event.description }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
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

            <div class="mt-6 grid gap-4 md:grid-cols-3">
                <div
                    class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-3 md:col-span-1"
                >
                    <div
                        class="aspect-16/10 overflow-hidden rounded-lg border border-sidebar-border/70 bg-muted/40"
                    >
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
                            No cover image
                        </div>
                    </div>

                    <form class="mt-3 space-y-3" @submit.prevent="submitCover">
                        <div class="grid gap-2">
                            <label
                                class="text-xs font-medium text-muted-foreground"
                                >Upload Cover / Thumbnail</label
                            >
                            <Input
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp"
                                @change="onCoverChange"
                            />
                            <p class="text-xs text-muted-foreground">
                                JPG, PNG, WEBP (max 2 MB)
                            </p>
                            <p
                                v-if="coverForm.errors.cover_image"
                                class="text-xs text-destructive"
                            >
                                {{ coverForm.errors.cover_image }}
                            </p>
                        </div>

                        <Button
                            type="submit"
                            size="sm"
                            :disabled="
                                coverForm.processing || !coverForm.cover_image
                            "
                        >
                            Upload Cover
                        </Button>
                    </form>
                </div>

                <div class="grid gap-4 text-sm md:col-span-2 md:grid-cols-2">
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">Status</div>
                        <div class="mt-2">
                            <Badge variant="outline">{{
                                props.event.status_label
                            }}</Badge>
                        </div>
                    </div>
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">Slug</div>
                        <div class="mt-2 font-medium text-foreground">
                            {{ props.event.slug }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Order Open
                        </div>
                        <div class="mt-2 font-medium text-foreground">
                            {{ formatDateTime(props.event.order_open_at) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Order Close
                        </div>
                        <div class="mt-2 font-medium text-foreground">
                            {{ formatDateTime(props.event.order_close_at) }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Expected Delivery
                        </div>
                        <div class="mt-2 font-medium text-foreground">
                            {{ props.event.expected_delivery_date || '-' }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Cycle Name
                        </div>
                        <div class="mt-2 font-medium text-foreground">
                            {{ props.event.fund_cycle.name || '-' }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Cycle Status
                        </div>
                        <div class="mt-2 font-medium text-foreground">
                            {{ props.event.fund_cycle.status || '-' }}
                        </div>
                    </div>
                    <div class="rounded-xl border border-sidebar-border/70 p-4">
                        <div class="text-xs text-muted-foreground">
                            Created At
                        </div>
                        <div class="mt-2 font-medium text-foreground">
                            {{ props.event.created_at || '-' }}
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
