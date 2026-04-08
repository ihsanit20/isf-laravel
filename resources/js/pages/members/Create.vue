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

type RelationshipOption = 'self' | 'spouse' | 'child' | 'parent' | 'other';
type PaymentMethod =
    | 'bank_transfer'
    | 'cash_deposit'
    | 'mobile_banking'
    | 'other';

type Props = {
    relationshipOptions: RelationshipOption[];
    paymentMethods: PaymentMethod[];
    registrationFeeAmount: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Membership',
                href: '/my-membership',
            },
            {
                title: 'Membership Form',
                href: '/my-membership/create',
            },
        ],
    },
});

const props = defineProps<Props>();

const form = useForm<{
    full_name: string;
    phone: string;
    relationship_to_user: RelationshipOption;
    units: number;
    registration_fee_payment_method: PaymentMethod;
    registration_fee_reference_no: string;
    registration_fee_proof: File | null;
}>({
    full_name: '',
    phone: '',
    relationship_to_user: 'self',
    units: 1,
    registration_fee_payment_method: 'bank_transfer',
    registration_fee_reference_no: '',
    registration_fee_proof: null,
});

const relationshipLabel = (value: RelationshipOption): string =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const paymentMethodLabel = (value: PaymentMethod): string =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const handleFeeProofChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.registration_fee_proof = target.files?.[0] ?? null;
};

const submit = () => {
    form.post('/my-membership', {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Membership Form" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-emerald-50 p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-semibold tracking-tight">
                        Membership Form
                    </h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        Submit the required membership details for yourself or a
                        family member. The one-time registration fee proof is
                        submitted with this application and reviewed together by
                        the admin.
                    </p>
                </div>

                <Button as-child variant="outline">
                    <Link href="/my-membership">
                        <ArrowLeft class="size-4" />
                        Back to My Membership
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
                            <Label for="member-full-name">Full name</Label>
                            <Input
                                id="member-full-name"
                                v-model="form.full_name"
                                placeholder="Member full name"
                            />
                            <InputError :message="form.errors.full_name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="member-phone">Phone</Label>
                            <Input
                                id="member-phone"
                                v-model="form.phone"
                                type="tel"
                                placeholder="01XXXXXXXXX"
                            />
                            <InputError :message="form.errors.phone" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="member-relationship"
                                >Relationship</Label
                            >
                            <Select v-model="form.relationship_to_user">
                                <SelectTrigger
                                    id="member-relationship"
                                    class="w-full"
                                >
                                    <SelectValue
                                        placeholder="Select relationship"
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="relationship in props.relationshipOptions"
                                        :key="relationship"
                                        :value="relationship"
                                    >
                                        {{ relationshipLabel(relationship) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="form.errors.relationship_to_user"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="member-units">Monthly Units</Label>
                            <Input
                                id="member-units"
                                v-model.number="form.units"
                                type="number"
                                min="1"
                            />
                            <p class="text-xs text-muted-foreground">
                                Monthly savings are calculated as units × 1000
                                BDT.
                            </p>
                            <InputError :message="form.errors.units" />
                        </div>

                        <div
                            class="rounded-2xl border border-border/70 bg-muted/20 p-4"
                        >
                            <p class="text-sm font-medium text-foreground">
                                Registration Fee
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                A one-time registration fee of
                                {{
                                    props.registrationFeeAmount.toLocaleString()
                                }}
                                BDT must be paid with this membership
                                application.
                            </p>
                        </div>

                        <div class="grid gap-2">
                            <Label for="registration-fee-method"
                                >Registration Fee Payment Method</Label
                            >
                            <Select
                                v-model="form.registration_fee_payment_method"
                            >
                                <SelectTrigger
                                    id="registration-fee-method"
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
                            <InputError
                                :message="
                                    form.errors.registration_fee_payment_method
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="registration-fee-reference-no"
                                >Registration Fee Reference No</Label
                            >
                            <Input
                                id="registration-fee-reference-no"
                                v-model="form.registration_fee_reference_no"
                                placeholder="Bank reference or transaction ID"
                            />
                            <InputError
                                :message="
                                    form.errors.registration_fee_reference_no
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="registration-fee-proof"
                                >Registration Fee Proof</Label
                            >
                            <Input
                                id="registration-fee-proof"
                                type="file"
                                accept=".jpg,.jpeg,.png,.pdf"
                                @input="handleFeeProofChange"
                            />
                            <p class="text-xs text-muted-foreground">
                                Upload the payment proof for the one-time 100
                                BDT registration fee.
                            </p>
                            <InputError
                                :message="form.errors.registration_fee_proof"
                            />
                        </div>

                        <div class="pt-2">
                            <Button type="submit" :disabled="form.processing">
                                Submit Membership Application
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
                        Review Process
                    </p>
                    <div class="mt-5 space-y-4 text-sm">
                        <div
                            class="rounded-2xl bg-background px-4 py-4 shadow-sm"
                        >
                            <p class="font-medium">1. Submission</p>
                            <p class="mt-1 leading-6 text-muted-foreground">
                                Membership details and registration fee proof
                                are submitted together in one application.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-background px-4 py-4 shadow-sm"
                        >
                            <p class="font-medium">2. Review</p>
                            <p class="mt-1 leading-6 text-muted-foreground">
                                An administrator reviews the member details and
                                fee proof in a single step.
                            </p>
                        </div>
                        <div
                            class="rounded-2xl bg-background px-4 py-4 shadow-sm"
                        >
                            <p class="font-medium">3. Status Update</p>
                            <p class="mt-1 leading-6 text-muted-foreground">
                                Once approved, the member becomes active and the
                                latest decision appears on the My Membership
                                page.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>
