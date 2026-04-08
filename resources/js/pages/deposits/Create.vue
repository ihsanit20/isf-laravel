<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
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
                title: 'My Deposits',
                href: '/my-deposits',
            },
            {
                title: 'Submit Deposit',
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

const submit = () => {
    form.post('/my-deposits', {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Submit Deposit" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-sky-50 p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-semibold tracking-tight">
                        Submit Deposit
                    </h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        Upload one bank deposit proof for the total amount you
                        transferred. After verification, you can split that
                        amount across your approved members.
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link href="/my-deposits">
                        <ArrowLeft class="size-4" />
                        Back to My Deposits
                    </Link>
                </Button>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-[28px] border border-sidebar-border/70 bg-background shadow-sm"
        >
            <div class="grid gap-0 lg:grid-cols-[1.15fr_0.85fr]">
                <form class="p-6 md:p-8" @submit.prevent="submit">
                    <div class="grid gap-5">
                        <div class="grid gap-2">
                            <Label for="deposit-amount">Deposit amount</Label>
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
                            <Label for="deposit-method">Payment method</Label>
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
                                        {{ paymentMethodLabel(paymentMethod) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.payment_method" />
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
                                <Label for="deposit-date">Deposit date</Label>
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
                                Accepted formats: JPG, PNG, or PDF up to 5 MB.
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
                            <Button type="submit" :disabled="form.processing">
                                Submit Deposit
                            </Button>
                        </div>
                    </div>
                </form>

                <div
                    class="border-t border-sidebar-border/70 bg-muted/30 p-6 md:p-8 lg:border-t-0 lg:border-l"
                >
                    <p
                        class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                    >
                        How It Works
                    </p>
                    <div class="mt-5 space-y-4 text-sm">
                        <div
                            class="rounded-2xl bg-background px-4 py-4 shadow-sm"
                        >
                            <p class="font-medium">1. Submit total amount</p>
                            <p class="mt-1 leading-6 text-muted-foreground">
                                Deposit once through the bank channel and submit
                                the total paid amount.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-background px-4 py-4 shadow-sm"
                        >
                            <p class="font-medium">2. Wait for verification</p>
                            <p class="mt-1 leading-6 text-muted-foreground">
                                An admin verifies only the uploaded proof and
                                the total amount.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-background px-4 py-4 shadow-sm"
                        >
                            <p class="font-medium">3. Allocate later</p>
                            <p class="mt-1 leading-6 text-muted-foreground">
                                After verification, you can split the deposit by
                                member and month without uploading another
                                proof.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
