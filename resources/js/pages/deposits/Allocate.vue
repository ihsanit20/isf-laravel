<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

type DepositItem = {
    total_deposit_amount: number;
    total_verified_amount: number;
    total_charge_allocated_amount: number;
    total_allocated_amount: number;
    total_allocatable_amount: number;
    total_deposit_count: number;
    can_allocate: boolean;
};

type Props = {
    summary: DepositItem;
    charges: {
        id: number;
        amount: number;
        category_title: string | null;
        category_code: string | null;
        member_name: string | null;
        effective_at: string | null;
    }[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Deposits',
                href: '/my-deposits',
            },
            {
                title: 'Settle Charges',
                href: '#',
            },
        ],
    },
});

const props = defineProps<Props>();

const form = useForm<{ charge_ids: string[] }>({
    charge_ids: [],
});

const totalAllocatedDraftAmount = computed(() =>
    props.charges
        .filter((charge) => form.charge_ids.includes(String(charge.id)))
        .reduce((sum, charge) => sum + charge.amount, 0),
);

const remainingAfterDraft = computed(
    () =>
        props.summary.total_allocatable_amount -
        totalAllocatedDraftAmount.value,
);

const submit = () => {
    form.transform((data) => ({
        charge_ids: data.charge_ids.map((id) => Number(id)),
    })).post('/my-deposits/allocate', {
        preserveScroll: true,
    });
};

const money = (amount: number): string => `${amount.toLocaleString()} BDT`;
</script>

<template>
    <Head title="Settle Charges" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-[28px] border border-sidebar-border/70 bg-linear-to-br from-background via-background to-emerald-50 p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="text-3xl font-semibold tracking-tight">
                        Settle Charges
                    </h1>
                    <p class="mt-3 text-sm leading-6 text-muted-foreground">
                        Settle pending charges from your total verified deposit
                        pool. Deposit submissions remain independent and are not
                        allocated one by one.
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

        <section class="grid gap-4 xl:grid-cols-[1.15fr_0.85fr]">
            <div
                class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
            >
                <form class="space-y-5" @submit.prevent="submit">
                    <div
                        class="rounded-3xl border border-border/70 bg-muted/20 p-4"
                    >
                        <div>
                            <h2 class="text-lg font-semibold tracking-tight">
                                Pending Charges
                            </h2>
                            <p class="mt-1 text-sm text-muted-foreground">
                                Select any due charges you want to settle from
                                the shared verified deposit pool.
                            </p>
                        </div>

                        <div class="mt-4 space-y-3">
                            <p
                                v-if="props.charges.length === 0"
                                class="rounded-2xl border border-dashed border-border/70 bg-background px-4 py-4 text-sm text-muted-foreground"
                            >
                                No pending charges are available right now.
                            </p>

                            <label
                                v-for="charge in props.charges"
                                :key="charge.id"
                                class="flex cursor-pointer items-start gap-3 rounded-2xl border border-border/70 bg-background px-4 py-4"
                            >
                                <input
                                    v-model="form.charge_ids"
                                    :value="String(charge.id)"
                                    type="checkbox"
                                    class="mt-1"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium text-foreground">
                                        {{ charge.category_title || 'Charge' }}
                                    </div>
                                    <div
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            charge.member_name ||
                                            'Unknown member'
                                        }}
                                        <span v-if="charge.effective_at"
                                            >• {{ charge.effective_at }}</span
                                        >
                                    </div>
                                </div>
                                <div
                                    class="text-right font-medium text-foreground"
                                >
                                    {{ money(charge.amount) }}
                                </div>
                            </label>
                        </div>

                        <InputError :message="form.errors.charge_ids" />
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <Button
                            type="submit"
                            :disabled="
                                form.processing ||
                                remainingAfterDraft < 0 ||
                                props.charges.length === 0
                            "
                        >
                            Confirm Charge Settlement
                        </Button>
                    </div>
                </form>
            </div>

            <div class="space-y-4">
                <section
                    class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        Allocation Pool Summary
                    </h2>
                    <div class="mt-4 grid gap-3 text-sm">
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Total deposited
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{ money(props.summary.total_deposit_amount) }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Verified deposits
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{ money(props.summary.total_verified_amount) }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Charges settled
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{
                                    money(props.summary.total_allocated_amount)
                                }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Available balance
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{
                                    money(
                                        props.summary.total_allocatable_amount,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                Draft charge settlement
                            </p>
                            <p class="mt-2 font-semibold text-foreground">
                                {{ money(totalAllocatedDraftAmount) }}
                            </p>
                        </div>
                        <div class="rounded-2xl bg-muted/30 px-4 py-4">
                            <p class="text-xs text-muted-foreground">
                                After confirm
                            </p>
                            <p
                                class="mt-2 font-semibold"
                                :class="
                                    remainingAfterDraft < 0
                                        ? 'text-rose-600'
                                        : 'text-foreground'
                                "
                            >
                                {{ money(remainingAfterDraft) }}
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    v-if="props.charges.length > 0"
                    class="rounded-[28px] border border-sidebar-border/70 bg-background p-6 shadow-sm"
                >
                    <h2 class="text-lg font-semibold tracking-tight">
                        Pending Charges
                    </h2>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="charge in props.charges"
                            :key="charge.id"
                            class="rounded-2xl border border-border/70 px-4 py-4"
                        >
                            <p class="font-medium text-foreground">
                                {{ charge.category_title || 'Charge' }}
                            </p>
                            <p class="mt-1 text-sm text-muted-foreground">
                                {{ charge.member_name || 'Unknown member' }} •
                                {{ money(charge.amount) }}
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </div>
</template>
