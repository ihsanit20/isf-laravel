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

type ExpenseCategoryOption = {
    value: string;
    label: string;
};

type EditableEventExpense = {
    id: number;
    expense_date: string;
    category: string;
    amount: number;
    description: string | null;
    receipt_path: string | null;
    receipt_url?: string | null;
};

type Props = {
    eventId: number;
    mode: 'create' | 'edit';
    expenseCategories: ExpenseCategoryOption[];
    eventExpense?: EditableEventExpense | null;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const today = new Date().toISOString().slice(0, 10);

const isEditing = computed(
    () => props.mode === 'edit' && !!props.eventExpense,
);

const form = useForm<{
    expense_date: string;
    category: string;
    amount: string;
    description: string;
    receipt: File | null;
}>({
    expense_date: today,
    category: props.expenseCategories[0]?.value ?? 'other',
    amount: '',
    description: '',
    receipt: null,
});

const resetFormState = () => {
    const values =
        isEditing.value && props.eventExpense
            ? {
                  expense_date: props.eventExpense.expense_date,
                  category: props.eventExpense.category,
                  amount: String(props.eventExpense.amount),
                  description: props.eventExpense.description ?? '',
                  receipt: null,
              }
            : {
                  expense_date: today,
                  category: props.expenseCategories[0]?.value ?? 'other',
                  amount: '',
                  description: '',
                  receipt: null,
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
        amount: Number(form.amount),
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => closeDialog(),
    };

    if (isEditing.value && props.eventExpense) {
        form.transform(() => ({
            ...payload,
            _method: 'put',
        })).post(
            `/admin/events/${props.eventId}/expenses/${props.eventExpense.id}`,
            {
                ...options,
                forceFormData: true,
            },
        );

        return;
    }

    form.transform(() => payload).post(
        `/admin/events/${props.eventId}/expenses`,
        {
            ...options,
            forceFormData: true,
        },
    );
};

const handleReceiptChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.receipt = target.files?.[0] ?? null;
};

watch(
    () => [
        isOpen.value,
        props.mode,
        props.eventExpense?.id,
        props.expenseCategories.map((category) => category.value).join(','),
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
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit Event Cost' : 'Add Event Cost' }}
                </DialogTitle>
                <DialogDescription>
                    Record money spent on this event (operational investment).
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="event-expense-date">Expense Date</Label>
                        <Input
                            id="event-expense-date"
                            v-model="form.expense_date"
                            type="date"
                        />
                        <InputError :message="form.errors.expense_date" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="event-expense-category">Category</Label>
                        <Select v-model="form.category">
                            <SelectTrigger
                                id="event-expense-category"
                                class="w-full"
                            >
                                <SelectValue placeholder="Select category" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="category in expenseCategories"
                                    :key="category.value"
                                    :value="category.value"
                                >
                                    {{ category.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.category" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="event-expense-amount">Amount (BDT)</Label>
                    <Input
                        id="event-expense-amount"
                        v-model="form.amount"
                        type="number"
                        min="1"
                        placeholder="1000"
                    />
                    <InputError :message="form.errors.amount" />
                </div>

                <div class="grid gap-2">
                    <Label for="event-expense-description">Description</Label>
                    <textarea
                        id="event-expense-description"
                        v-model="form.description"
                        rows="4"
                        class="flex min-h-24 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
                        placeholder="Write a short note about this cost"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-2">
                    <Label for="event-expense-receipt">Receipt</Label>
                    <Input
                        id="event-expense-receipt"
                        type="file"
                        accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                        @input="handleReceiptChange"
                    />
                    <p class="text-xs text-muted-foreground">
                        Optional. JPG, PNG, PDF, DOC, or DOCX up to 5 MB.
                    </p>
                    <a
                        v-if="eventExpense?.receipt_url"
                        :href="eventExpense.receipt_url"
                        target="_blank"
                        class="text-sm text-primary underline underline-offset-4"
                    >
                        View current receipt
                    </a>
                    <InputError :message="form.errors.receipt" />
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
                        {{ isEditing ? 'Save Changes' : 'Add Cost' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
