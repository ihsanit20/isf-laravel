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

type PackageStatusOption = {
    value: string;
    label: string;
};

type EditablePackage = {
    id: number;
    name: string;
    description: string | null;
    unit_price: string;
    advance_percent: string;
    min_qty_per_order: number;
    max_qty_per_order: number | null;
    stock_qty: number | null;
    sort_order: number;
    status: string;
};

type Props = {
    eventId: number;
    mode: 'create' | 'edit';
    packageStatuses: PackageStatusOption[];
    eventPackage?: EditablePackage | null;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const isEditing = computed(() => props.mode === 'edit' && !!props.eventPackage);

const form = useForm<{
    name: string;
    description: string;
    unit_price: string;
    advance_percent: string;
    min_qty_per_order: string;
    max_qty_per_order: string;
    stock_qty: string;
    sort_order: string;
    status: string;
}>({
    name: '',
    description: '',
    unit_price: '',
    advance_percent: '0',
    min_qty_per_order: '1',
    max_qty_per_order: '',
    stock_qty: '',
    sort_order: '0',
    status: props.packageStatuses[0]?.value ?? 'draft',
});

const resetFormState = () => {
    const values =
        isEditing.value && props.eventPackage
            ? {
                  name: props.eventPackage.name,
                  description: props.eventPackage.description ?? '',
                  unit_price: props.eventPackage.unit_price,
                  advance_percent: props.eventPackage.advance_percent,
                  min_qty_per_order: String(
                      props.eventPackage.min_qty_per_order,
                  ),
                  max_qty_per_order:
                      props.eventPackage.max_qty_per_order !== null
                          ? String(props.eventPackage.max_qty_per_order)
                          : '',
                  stock_qty:
                      props.eventPackage.stock_qty !== null
                          ? String(props.eventPackage.stock_qty)
                          : '',
                  sort_order: String(props.eventPackage.sort_order),
                  status: props.eventPackage.status,
              }
            : {
                  name: '',
                  description: '',
                  unit_price: '',
                  advance_percent: '0',
                  min_qty_per_order: '1',
                  max_qty_per_order: '',
                  stock_qty: '',
                  sort_order: '0',
                  status: props.packageStatuses[0]?.value ?? 'draft',
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
        description: form.description || null,
        unit_price: form.unit_price,
        advance_percent: form.advance_percent,
        min_qty_per_order: form.min_qty_per_order,
        max_qty_per_order: form.max_qty_per_order || null,
        stock_qty: form.stock_qty || null,
        sort_order: form.sort_order || '0',
        status: form.status,
    };

    if (isEditing.value && props.eventPackage) {
        form.transform(() => payload).put(
            `/admin/events/${props.eventId}/packages/${props.eventPackage.id}`,
            {
                preserveScroll: true,
                onSuccess: () => closeDialog(),
            },
        );

        return;
    }

    form.transform(() => payload).post(
        `/admin/events/${props.eventId}/packages`,
        {
            preserveScroll: true,
            onSuccess: () => closeDialog(),
        },
    );
};

watch(
    () => [isOpen.value, props.mode, props.eventPackage?.id],
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
                    {{ isEditing ? 'Edit Package' : 'Add Package' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the package details below.'
                            : 'Add a new package to this event.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4 py-2" @submit.prevent="submit">
                <!-- Name -->
                <div class="grid gap-1.5">
                    <Label for="pkg-name">Package Name</Label>
                    <Input
                        id="pkg-name"
                        v-model="form.name"
                        placeholder="e.g. Ghee 1kg Pack"
                        :disabled="form.processing"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <!-- Description -->
                <div class="grid gap-1.5">
                    <Label for="pkg-desc"
                        >Description
                        <span class="text-xs text-muted-foreground"
                            >(optional)</span
                        ></Label
                    >
                    <textarea
                        id="pkg-desc"
                        v-model="form.description"
                        placeholder="Short description..."
                        rows="2"
                        :disabled="form.processing"
                        class="flex min-h-15 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <!-- Price + Advance -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="pkg-price">Unit Price (৳)</Label>
                        <Input
                            id="pkg-price"
                            v-model="form.unit_price"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.unit_price" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pkg-advance">Advance % </Label>
                        <Input
                            id="pkg-advance"
                            v-model="form.advance_percent"
                            type="number"
                            min="0"
                            max="100"
                            step="0.01"
                            placeholder="0"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.advance_percent" />
                    </div>
                </div>

                <!-- Qty Rules -->
                <div class="grid grid-cols-3 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="pkg-min">Min Qty/Order</Label>
                        <Input
                            id="pkg-min"
                            v-model="form.min_qty_per_order"
                            type="number"
                            min="1"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.min_qty_per_order" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pkg-max"
                            >Max Qty/Order
                            <span class="text-xs text-muted-foreground"
                                >(blank = no limit)</span
                            ></Label
                        >
                        <Input
                            id="pkg-max"
                            v-model="form.max_qty_per_order"
                            type="number"
                            min="1"
                            placeholder="—"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.max_qty_per_order" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pkg-stock"
                            >Stock
                            <span class="text-xs text-muted-foreground"
                                >(blank = unlimited)</span
                            ></Label
                        >
                        <Input
                            id="pkg-stock"
                            v-model="form.stock_qty"
                            type="number"
                            min="0"
                            placeholder="—"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.stock_qty" />
                    </div>
                </div>

                <!-- Sort + Status -->
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-1.5">
                        <Label for="pkg-sort">Sort Order</Label>
                        <Input
                            id="pkg-sort"
                            v-model="form.sort_order"
                            type="number"
                            min="0"
                            :disabled="form.processing"
                        />
                        <InputError :message="form.errors.sort_order" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pkg-status">Status</Label>
                        <Select
                            v-model="form.status"
                            :disabled="form.processing"
                        >
                            <SelectTrigger id="pkg-status">
                                <SelectValue placeholder="Select status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="s in props.packageStatuses"
                                    :key="s.value"
                                    :value="s.value"
                                >
                                    {{ s.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>
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
                                  ? 'Update Package'
                                  : 'Add Package'
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
