<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type EventStatusOption = {
    value: string;
    label: string;
};

type EditableFundCycleEvent = {
    id: number;
    title: string;
    status: string;
    description: string | null;
    order_open_at: string;
    order_close_at: string;
    expected_delivery_date: string | null;
};

type Props = {
    fundCycleId: number;
    mode: 'create' | 'edit';
    eventStatuses: EventStatusOption[];
    fundCycleEvent?: EditableFundCycleEvent | null;
    updateUrl?: string | null;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const isEditing = computed(
    () => props.mode === 'edit' && !!props.fundCycleEvent,
);

const form = useForm<{
    title: string;
    status: string;
    description: string;
    order_open_at: string;
    order_close_at: string;
    expected_delivery_date: string;
}>({
    title: '',
    status: props.eventStatuses[0]?.value ?? 'draft',
    description: '',
    order_open_at: '',
    order_close_at: '',
    expected_delivery_date: '',
});

const resetFormState = () => {
    const values =
        isEditing.value && props.fundCycleEvent
            ? {
                  title: props.fundCycleEvent.title,
                  status: props.fundCycleEvent.status,
                  description: props.fundCycleEvent.description ?? '',
                  order_open_at: props.fundCycleEvent.order_open_at,
                  order_close_at: props.fundCycleEvent.order_close_at,
                  expected_delivery_date:
                      props.fundCycleEvent.expected_delivery_date ?? '',
              }
            : {
                  title: '',
                  status: props.eventStatuses[0]?.value ?? 'draft',
                  description: '',
                  order_open_at: '',
                  order_close_at: '',
                  expected_delivery_date: '',
              };

    form.defaults(values);
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    isOpen.value = false;
    resetFormState();
};

const submit = () => {
    const payload = {
        ...form.data(),
        description: form.description || null,
        expected_delivery_date: form.expected_delivery_date || null,
    };

    if (isEditing.value && props.fundCycleEvent) {
        const updateUrl =
            props.updateUrl ??
            `/admin/fund-cycles/${props.fundCycleId}/events/${props.fundCycleEvent.id}`;

        form.transform(() => payload).put(updateUrl, {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        });

        return;
    }

    form.transform(() => payload).post(
        `/admin/fund-cycles/${props.fundCycleId}/events`,
        {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        },
    );
};

watch(
    () => [
        isOpen.value,
        props.mode,
        props.fundCycleEvent?.id,
        props.eventStatuses.map((status) => status.value).join(','),
    ],
    ([open]) => {
        if (open) {
            resetFormState();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Event' : 'Add Event' }}
                </DialogTitle>
                <DialogDescription>
                    Keep event details simple and clear for package and order
                    setup.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="event-title">Title</Label>
                    <Input
                        id="event-title"
                        v-model="form.title"
                        placeholder="Eid Special Grocery Pre-order"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="event-status">Status</Label>
                    <Select v-model="form.status">
                        <SelectTrigger id="event-status" class="w-full">
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="status in eventStatuses"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.status" />
                </div>

                <div class="grid gap-2 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="event-order-open">Order Open At</Label>
                        <Input
                            id="event-order-open"
                            v-model="form.order_open_at"
                            type="datetime-local"
                        />
                        <InputError :message="form.errors.order_open_at" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="event-order-close">Order Close At</Label>
                        <Input
                            id="event-order-close"
                            v-model="form.order_close_at"
                            type="datetime-local"
                        />
                        <InputError :message="form.errors.order_close_at" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="event-expected-delivery"
                        >Expected Delivery Date</Label
                    >
                    <Input
                        id="event-expected-delivery"
                        v-model="form.expected_delivery_date"
                        type="date"
                    />
                    <InputError :message="form.errors.expected_delivery_date" />
                </div>

                <div class="grid gap-2">
                    <Label for="event-description">Description</Label>
                    <textarea
                        id="event-description"
                        v-model="form.description"
                        rows="4"
                        class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Short details about this event"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="closeDialog"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Save Changes' : 'Add Event' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
