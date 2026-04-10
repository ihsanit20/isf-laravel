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

type EditableChargeCategory = {
    id: number;
    code: string;
    title: string;
    default_amount: number;
    is_active: boolean;
    is_system: boolean;
};

type Props = {
    mode: 'create' | 'edit';
    chargeCategory?: EditableChargeCategory | null;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const isEditing = computed(
    () => props.mode === 'edit' && !!props.chargeCategory,
);

const form = useForm<{
    code: string;
    title: string;
    default_amount: string;
    is_active: '1' | '0';
}>({
    code: '',
    title: '',
    default_amount: '',
    is_active: '1',
});

const resetFormState = () => {
    const values =
        isEditing.value && props.chargeCategory
            ? {
                  code: props.chargeCategory.code,
                  title: props.chargeCategory.title,
                  default_amount: String(props.chargeCategory.default_amount),
                  is_active: props.chargeCategory.is_active ? '1' : '0',
              }
            : {
                  code: '',
                  title: '',
                  default_amount: '',
                  is_active: '1' as const,
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
        default_amount: Number(form.default_amount),
        is_active: form.is_active === '1',
    };

    if (isEditing.value && props.chargeCategory) {
        form.transform(() => payload).put(
            `/admin/charge-categories/${props.chargeCategory.id}`,
            {
                preserveScroll: true,
                onSuccess: () => closeDialog(),
            },
        );

        return;
    }

    form.transform(() => payload).post('/admin/charge-categories', {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    });
};

watch(
    () => [isOpen.value, props.mode, props.chargeCategory?.id],
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
                    {{
                        isEditing
                            ? 'Edit Charge Category'
                            : 'Add Charge Category'
                    }}
                </DialogTitle>
                <DialogDescription>
                    Define reusable charge types and the default amount for new
                    member charges.
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="charge-category-code">Code</Label>
                    <Input
                        id="charge-category-code"
                        v-model="form.code"
                        placeholder="registration_fee"
                        :disabled="chargeCategory?.is_system"
                    />
                    <InputError :message="form.errors.code" />
                </div>

                <div class="grid gap-2">
                    <Label for="charge-category-title">Title</Label>
                    <Input
                        id="charge-category-title"
                        v-model="form.title"
                        placeholder="Registration Fee"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="charge-category-amount">Default Amount</Label>
                    <Input
                        id="charge-category-amount"
                        v-model="form.default_amount"
                        type="number"
                        min="1"
                        placeholder="100"
                    />
                    <InputError :message="form.errors.default_amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="charge-category-active">Status</Label>
                    <Select v-model="form.is_active">
                        <SelectTrigger
                            id="charge-category-active"
                            class="w-full"
                            :disabled="chargeCategory?.is_system"
                        >
                            <SelectValue placeholder="Select status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="1">Active</SelectItem>
                            <SelectItem value="0">Inactive</SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.is_active" />
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
                        {{ isEditing ? 'Save Changes' : 'Add Category' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
