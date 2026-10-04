<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type PaymentMethod =
    | 'bank_transfer'
    | 'cash_deposit'
    | 'mobile_banking'
    | 'other';

type Props = {
    paymentMethods: PaymentMethod[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Deposits',
                href: '/my-deposits',
            },
            {
                title: 'New Deposit',
                href: '/my-deposits/create',
            },
        ],
    },
});

const props = defineProps<Props>();

const form = useForm<{
    amount: number | '';
    payment_method: PaymentMethod;
    reference_no: string;
    deposit_date: string;
    proof: File | null;
    notes: string;
}>({
    amount: '',
    payment_method: 'bank_transfer',
    reference_no: '',
    deposit_date: new Date().toISOString().slice(0, 10),
    proof: null,
    notes: '',
});

const paymentMethodLabel = (value: PaymentMethod): string =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const handleProofChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.proof = target.files?.[0] ?? null;
};

const steps = [
    {
        title: 'Admin verifies',
        description: 'An admin checks the proof against the ISF account.',
    },
    {
        title: 'Balance goes up',
        description: 'The verified amount becomes your available balance.',
    },
    {
        title: 'Pay charges and invest',
        description:
            'Use the balance for member charges and to invest each member in a fund cycle.',
    },
];

const submit = () => {
    form.post('/my-deposits', {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="New Deposit" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="New deposit"
            description="Submit one proof for the total amount you transferred to the ISF account."
        >
            <template #actions>
                <Button as-child variant="outline">
                    <Link href="/my-deposits">
                        <ArrowLeft class="size-4" />
                        Deposits
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
            <Card>
                <CardContent>
                    <form @submit.prevent="submit">
                        <div class="grid gap-5">
                            <div class="grid gap-2">
                                <Label for="deposit-amount"
                                    >Deposit amount</Label
                                >
                                <Input
                                    id="deposit-amount"
                                    v-model.number="form.amount"
                                    type="number"
                                    min="1"
                                    placeholder="Enter total deposited amount"
                                />
                                <InputError :message="form.errors.amount" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="deposit-method"
                                    >Payment method</Label
                                >
                                <Select v-model="form.payment_method">
                                    <SelectTrigger
                                        id="deposit-method"
                                        class="w-full"
                                    >
                                        <SelectValue
                                            placeholder="Select payment method"
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem
                                            v-for="paymentMethod in props.paymentMethods"
                                            :key="paymentMethod"
                                            :value="paymentMethod"
                                        >
                                            {{
                                                paymentMethodLabel(
                                                    paymentMethod,
                                                )
                                            }}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                <InputError
                                    :message="form.errors.payment_method"
                                />
                            </div>

                            <div class="grid gap-2 md:grid-cols-2 md:gap-5">
                                <div class="grid gap-2">
                                    <Label for="deposit-reference-no"
                                        >Reference no</Label
                                    >
                                    <Input
                                        id="deposit-reference-no"
                                        v-model="form.reference_no"
                                        placeholder="Bank reference or transaction ID"
                                    />
                                    <InputError
                                        :message="form.errors.reference_no"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="deposit-date"
                                        >Deposit date</Label
                                    >
                                    <Input
                                        id="deposit-date"
                                        v-model="form.deposit_date"
                                        type="date"
                                    />
                                    <InputError
                                        :message="form.errors.deposit_date"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-2">
                                <Label for="deposit-proof">Deposit proof</Label>
                                <Input
                                    id="deposit-proof"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.pdf"
                                    @input="handleProofChange"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Accepted formats: JPG, PNG, or PDF up to 5
                                    MB.
                                </p>
                                <InputError :message="form.errors.proof" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="deposit-notes">Notes</Label>
                                <Input
                                    id="deposit-notes"
                                    v-model="form.notes"
                                    placeholder="Optional note for the admin reviewer"
                                />
                                <InputError :message="form.errors.notes" />
                            </div>

                            <div class="pt-2">
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                >
                                    Submit deposit
                                </Button>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card class="h-fit gap-4 bg-muted/30">
                <CardHeader>
                    <CardTitle class="text-base">What happens next</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="grid gap-4 text-sm">
                        <li
                            v-for="(step, index) in steps"
                            :key="step.title"
                            class="flex gap-3"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full border bg-background text-xs font-medium"
                            >
                                {{ index + 1 }}
                            </span>
                            <div>
                                <p class="font-medium">{{ step.title }}</p>
                                <p class="text-muted-foreground">
                                    {{ step.description }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
