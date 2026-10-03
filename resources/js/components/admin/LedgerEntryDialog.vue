<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';
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

/**
 * Generic money-entry form for ledger-backed records (incomes, charges,
 * business transactions, cycle transactions, refunds, payouts).
 */
export type LedgerField = {
    name: string;
    label: string;
    type: 'text' | 'number' | 'date' | 'select' | 'textarea';
    options?: { value: string; label: string }[];
    placeholder?: string;
    required?: boolean;
    help?: string;
};

type Props = {
    title: string;
    description?: string;
    action: string;
    method?: 'post' | 'put' | 'patch';
    fields: LedgerField[];
    initial?: Record<string, string | number | null | undefined>;
    extra?: Record<string, string | number>;
    submitLabel?: string;
};

const props = withDefaults(defineProps<Props>(), {
    description: '',
    method: 'post',
    initial: () => ({}),
    extra: () => ({}),
    submitLabel: 'Save',
});

const isOpen = defineModel<boolean>('isOpen', { default: false });

const today = new Date().toISOString().slice(0, 10);

const defaultsFor = (): Record<string, string> =>
    Object.fromEntries(
        props.fields.map((field) => {
            const initial = props.initial[field.name];

            if (initial !== undefined && initial !== null) {
                return [field.name, String(initial)];
            }

            if (field.type === 'date') {
                return [field.name, today];
            }

            if (field.type === 'select') {
                return [field.name, field.options?.[0]?.value ?? ''];
            }

            return [field.name, ''];
        }),
    );

const form = useForm<Record<string, string>>(defaultsFor());

const reset = () => {
    form.defaults(defaultsFor());
    form.reset();
    form.clearErrors();
};

const close = () => {
    isOpen.value = false;
    reset();
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        ...props.extra,
        ...(props.method !== 'post' ? { _method: props.method } : {}),
    })).post(props.action, {
        preserveScroll: true,
        onSuccess: () => close(),
    });
};

const fieldError = (name: string): string | undefined =>
    (form.errors as Record<string, string | undefined>)[name];

watch(
    () => [isOpen.value, props.action],
    ([open]) => {
        if (open) {
            reset();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ title }}</DialogTitle>
                <DialogDescription v-if="description">
                    {{ description }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div
                    v-for="field in fields"
                    :key="field.name"
                    class="grid gap-2"
                >
                    <Label :for="`ledger-field-${field.name}`">
                        {{ field.label }}
                    </Label>

                    <Select
                        v-if="field.type === 'select'"
                        v-model="form[field.name]"
                    >
                        <SelectTrigger
                            :id="`ledger-field-${field.name}`"
                            class="w-full"
                        >
                            <SelectValue :placeholder="field.placeholder" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in field.options"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <textarea
                        v-else-if="field.type === 'textarea'"
                        :id="`ledger-field-${field.name}`"
                        v-model="form[field.name]"
                        rows="3"
                        class="flex min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :placeholder="field.placeholder"
                    />

                    <Input
                        v-else
                        :id="`ledger-field-${field.name}`"
                        v-model="form[field.name]"
                        :type="field.type"
                        :step="field.type === 'number' ? '0.01' : undefined"
                        :min="field.type === 'number' ? '0' : undefined"
                        :placeholder="field.placeholder"
                        :required="field.required"
                    />

                    <p v-if="field.help" class="text-xs text-muted-foreground">
                        {{ field.help }}
                    </p>
                    <InputError :message="fieldError(field.name)" />
                </div>

                <InputError
                    v-for="key in Object.keys(form.errors).filter(
                        (key) => !fields.some((field) => field.name === key),
                    )"
                    :key="key"
                    :message="fieldError(key)"
                />

                <DialogFooter class="gap-2">
                    <Button type="button" variant="secondary" @click="close">
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ submitLabel }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
