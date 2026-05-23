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

type EditablePickupPoint = {
    id: number;
    name: string;
    area: string | null;
    address: string | null;
    contact_person: string | null;
    phone: string | null;
    sort_order: number;
    is_active: boolean;
};

type Props = {
    eventId: number;
    mode: 'create' | 'edit';
    pickupPoint?: EditablePickupPoint | null;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const isEditing = computed(() => props.mode === 'edit' && !!props.pickupPoint);

const form = useForm<{
    name: string;
    area: string;
    address: string;
    contact_person: string;
    phone: string;
    sort_order: string;
    is_active: boolean;
}>({
    name: '',
    area: '',
    address: '',
    contact_person: '',
    phone: '',
    sort_order: '0',
    is_active: true,
});

const resetFormState = () => {
    const values =
        isEditing.value && props.pickupPoint
            ? {
                  name: props.pickupPoint.name,
                  area: props.pickupPoint.area ?? '',
                  address: props.pickupPoint.address ?? '',
                  contact_person: props.pickupPoint.contact_person ?? '',
                  phone: props.pickupPoint.phone ?? '',
                  sort_order: String(props.pickupPoint.sort_order),
                  is_active: props.pickupPoint.is_active,
              }
            : {
                  name: '',
                  area: '',
                  address: '',
                  contact_person: '',
                  phone: '',
                  sort_order: '0',
                  is_active: true,
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
        name: form.name,
        area: form.area || null,
        address: form.address || null,
        contact_person: form.contact_person || null,
        phone: form.phone || null,
        sort_order: form.sort_order || '0',
        is_active: form.is_active,
    };

    if (isEditing.value && props.pickupPoint) {
        form.transform(() => payload).put(
            `/admin/events/${props.eventId}/pickup-points/${props.pickupPoint.id}`,
            {
                preserveScroll: true,
                onSuccess: () => closeDialog(),
            },
        );

        return;
    }

    form.transform(() => payload).post(
        `/admin/events/${props.eventId}/pickup-points`,
        {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        },
    );
};

watch(
    () => [isOpen.value, props.mode, props.pickupPoint?.id],
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
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Pickup Point' : 'Add Pickup Point' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the pickup point details below.'
                            : 'Add a new pickup point for this event.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4 py-2" @submit.prevent="submit">
                <!-- Name -->
                <div class="grid gap-1.5">
                    <Label for="pp-name">Pickup Point Name</Label>
                    <Input
                        id="pp-name"
                        v-model="form.name"
                        placeholder="e.g. Mirpur-10 Pickup Point"
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Area + Phone -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="pp-area">
                            Area / District
                            <span class="text-xs text-muted-foreground"
                                >(optional)</span
                            >
                        </Label>
                        <Input
                            id="pp-area"
                            v-model="form.area"
                            placeholder="e.g. Mirpur, Dhaka"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.area" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pp-phone">
                            Phone
                            <span class="text-xs text-muted-foreground"
                                >(optional)</span
                            >
                        </Label>
                        <Input
                            id="pp-phone"
                            v-model="form.phone"
                            placeholder="01XXXXXXXXX"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.phone" />
                    </div>
                </div>

                <!-- Address -->
                <div class="grid gap-1.5">
                    <Label for="pp-address">
                        Full Address
                        <span class="text-xs text-muted-foreground"
                            >(optional)</span
                        >
                    </Label>
                    <textarea
                        id="pp-address"
                        v-model="form.address"
                        placeholder="Full address or landmark..."
                        rows="2"
                        :disabled="form.processing"
                        class="flex min-h-15 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError :message="form.errors.address" />
                </div>

                <!-- Contact Person + Sort -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="pp-contact">
                            Contact Person
                            <span class="text-xs text-muted-foreground"
                                >(optional)</span
                            >
                        </Label>
                        <Input
                            id="pp-contact"
                            v-model="form.contact_person"
                            placeholder="Name"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.contact_person" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pp-sort">Sort Order</Label>
                        <Input
                            id="pp-sort"
                            v-model="form.sort_order"
                            type="number"
                            min="0"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>
                </div>

                <!-- Active toggle -->
                <div
                    class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 px-4 py-3"
                >
                    <input
                        id="pp-active"
                        v-model="form.is_active"
                        type="checkbox"
                        class="size-4 rounded border-input accent-primary"
                        :disabled="form.processing"
                    />
                    <Label for="pp-active" class="cursor-pointer font-normal">
                        Active (customers can select this point during ordering)
                    </Label>
                </div>

                <DialogFooter class="pt-2">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.processing"
                        @click="closeDialog"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{
                            form.processing
                                ? 'Saving...'
                                : isEditing
                                  ? 'Update Pickup Point'
                                  : 'Add Pickup Point'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
