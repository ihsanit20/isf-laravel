<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import {
    ArrowRight,
    CircleAlert,
    Phone,
    Plus,
    UsersRound,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import EmptyState from '@/components/shared/EmptyState.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatMoney, titleCase } from '@/lib/format';

type MemberStatus = 'pending' | 'approved' | 'rejected' | 'exited';
type ChargeStatus = 'pending' | 'posted' | 'waived' | 'cancelled';

type ChargeItem = {
    id: number;
    title: string | null;
    code: string | null;
    amount: number;
    status: ChargeStatus;
    effective_at: string | null;
    paid_at: string | null;
};

type MemberItem = {
    id: number;
    full_name: string;
    phone: string | null;
    relationship_to_user: string;
    units: number;
    status: MemberStatus;
    rejection_note: string | null;
    applied_at: string | null;
    approved_at: string | null;
    activated_at: string | null;
    registration_charge: {
        id: number;
        amount: number;
        status: ChargeStatus;
        paid_at: string | null;
    } | null;
    capital_in_cycles: number;
    charges: ChargeItem[];
};

type Props = {
    allocationSummary: {
        available_to_allocate: number;
    };
    members: MemberItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Members', href: '/my-membership' }],
    },
});

const props = defineProps<Props>();

const chargeStatusLabels: Record<ChargeStatus, string> = {
    pending: 'Unpaid',
    posted: 'Paid',
    waived: 'Waived',
    cancelled: 'Cancelled',
};

const isUnpaid = (charge: ChargeItem): boolean =>
    charge.status === 'pending' || charge.status === 'cancelled';

const isActive = (member: MemberItem): boolean =>
    member.status === 'approved' && member.activated_at !== null;

const unpaidTotal = (member: MemberItem): number =>
    member.charges
        .filter(isUnpaid)
        .reduce((sum, charge) => sum + charge.amount, 0);

const memberNote = (member: MemberItem): string | null => {
    if (member.status === 'pending') {
        return 'Waiting for admin approval.';
    }

    if (member.status === 'rejected') {
        return member.rejection_note
            ? `Rejected: ${member.rejection_note}`
            : 'This application was rejected.';
    }

    if (member.status === 'exited') {
        return 'This member has exited.';
    }

    if (isActive(member)) {
        return null;
    }

    if (!member.registration_charge) {
        return 'Approved. The registration fee will be added by an admin.';
    }

    return 'Approved. Pay the registration fee from your balance to activate this member and start investing.';
};

const payingMember = ref<MemberItem | null>(null);
const payingCharge = ref<ChargeItem | null>(null);
const isPayDialogOpen = ref(false);

const form = useForm<{ charge_ids: number[]; return_to: string }>({
    charge_ids: [],
    return_to: 'members',
});

const balanceAfterPayment = computed(
    () =>
        props.allocationSummary.available_to_allocate -
        (payingCharge.value?.amount ?? 0),
);

const openPayDialog = (member: MemberItem, charge: ChargeItem) => {
    payingMember.value = member;
    payingCharge.value = charge;
    form.charge_ids = [charge.id];
    form.clearErrors();
    isPayDialogOpen.value = true;
};

const payCharge = () => {
    form.post('/my-deposits/allocate', {
        preserveScroll: true,
        onSuccess: () => {
            isPayDialogOpen.value = false;
        },
    });
};
</script>

<template>
    <Head title="Members" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Members"
            description="Each member invests in fund cycles separately. A member can invest once approved and the registration fee is paid."
        >
            <template #actions>
                <Button as-child>
                    <Link href="/my-membership/create">
                        <Plus class="size-4" />
                        Add member
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <p class="text-sm text-muted-foreground">
            Available balance for charges and investments:
            <span class="font-medium text-foreground tabular-nums">
                {{ formatMoney(props.allocationSummary.available_to_allocate) }}
            </span>
        </p>

        <EmptyState
            v-if="props.members.length === 0"
            :icon="UsersRound"
            title="No members yet"
            description="Apply for a membership for yourself or a family member. After admin approval you can pay the registration fee and start investing."
        >
            <Button as-child>
                <Link href="/my-membership/create">Add member</Link>
            </Button>
        </EmptyState>

        <div v-else class="grid gap-4 xl:grid-cols-2">
            <Card
                v-for="member in props.members"
                :key="member.id"
                class="gap-4"
            >
                <CardHeader>
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <CardTitle class="truncate text-lg">
                                {{ member.full_name }}
                            </CardTitle>
                            <CardDescription
                                class="mt-1 flex flex-wrap gap-x-3"
                            >
                                <span>
                                    {{ titleCase(member.relationship_to_user) }}
                                </span>
                                <span
                                    v-if="member.phone"
                                    class="flex items-center gap-1"
                                >
                                    <Phone class="size-3" />
                                    {{ member.phone }}
                                </span>
                            </CardDescription>
                        </div>
                        <StatusBadge v-if="isActive(member)" status="active" />
                        <StatusBadge v-else :status="member.status" />
                    </div>
                </CardHeader>

                <CardContent class="grid gap-4">
                    <dl class="grid grid-cols-3 gap-3 text-sm">
                        <div class="rounded-lg bg-muted/50 px-3 py-2">
                            <dt class="text-xs text-muted-foreground">Units</dt>
                            <dd class="font-medium tabular-nums">
                                {{ member.units }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-muted/50 px-3 py-2">
                            <dt class="text-xs text-muted-foreground">
                                In cycles
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ formatMoney(member.capital_in_cycles) }}
                            </dd>
                        </div>
                        <div class="rounded-lg bg-muted/50 px-3 py-2">
                            <dt class="text-xs text-muted-foreground">
                                Unpaid charges
                            </dt>
                            <dd class="font-medium tabular-nums">
                                {{ formatMoney(unpaidTotal(member)) }}
                            </dd>
                        </div>
                    </dl>

                    <div
                        v-if="memberNote(member)"
                        class="flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" />
                        <p>{{ memberNote(member) }}</p>
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-medium">Charges</p>
                        <div class="rounded-lg border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-12 pl-3"
                                            >SL</TableHead
                                        >
                                        <TableHead>Charge</TableHead>
                                        <TableHead class="text-right">
                                            Amount
                                        </TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead class="pr-3 text-right" />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow
                                        v-for="(
                                            charge, index
                                        ) in member.charges"
                                        :key="charge.id"
                                    >
                                        <TableCell
                                            class="pl-3 text-muted-foreground tabular-nums"
                                            >{{ index + 1 }}</TableCell
                                        >
                                        <TableCell class="whitespace-normal">
                                            <p class="font-medium">
                                                {{ charge.title }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{
                                                    charge.paid_at
                                                        ? `Paid ${charge.paid_at}`
                                                        : charge.effective_at
                                                }}
                                            </p>
                                        </TableCell>
                                        <TableCell
                                            class="text-right tabular-nums"
                                        >
                                            {{ formatMoney(charge.amount) }}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge
                                                :status="charge.status"
                                                :label="
                                                    chargeStatusLabels[
                                                        charge.status
                                                    ]
                                                "
                                            />
                                        </TableCell>
                                        <TableCell class="pr-3 text-right">
                                            <Button
                                                v-if="
                                                    isUnpaid(charge) &&
                                                    member.status === 'approved'
                                                "
                                                size="sm"
                                                variant="outline"
                                                @click="
                                                    openPayDialog(
                                                        member,
                                                        charge,
                                                    )
                                                "
                                            >
                                                Pay
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                    <TableEmpty
                                        v-if="member.charges.length === 0"
                                        :colspan="5"
                                    >
                                        No charges.
                                    </TableEmpty>
                                </TableBody>
                            </Table>
                        </div>
                    </div>
                </CardContent>

                <CardFooter
                    class="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
                >
                    <span>
                        Applied {{ member.applied_at ?? '—' }}
                        <template v-if="member.activated_at">
                            · Active since {{ member.activated_at }}
                        </template>
                    </span>
                    <Button
                        v-if="isActive(member)"
                        as-child
                        size="sm"
                        variant="ghost"
                    >
                        <Link :href="`/my-allocations?member=${member.id}`">
                            Investments
                            <ArrowRight class="size-4" />
                        </Link>
                    </Button>
                </CardFooter>
            </Card>
        </div>

        <Dialog v-model:open="isPayDialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Pay {{ payingCharge?.title }}</DialogTitle>
                    <DialogDescription>
                        For {{ payingMember?.full_name }}. The amount is taken
                        from your available balance.
                    </DialogDescription>
                </DialogHeader>

                <dl class="grid gap-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Available balance</dt>
                        <dd class="tabular-nums">
                            {{
                                formatMoney(
                                    props.allocationSummary
                                        .available_to_allocate,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-muted-foreground">Charge</dt>
                        <dd class="tabular-nums">
                            − {{ formatMoney(payingCharge?.amount) }}
                        </dd>
                    </div>
                    <div class="flex justify-between border-t pt-2 font-medium">
                        <dt>Balance after</dt>
                        <dd
                            class="tabular-nums"
                            :class="
                                balanceAfterPayment < 0
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : ''
                            "
                        >
                            {{ formatMoney(balanceAfterPayment) }}
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="balanceAfterPayment < 0"
                    class="text-sm text-rose-600 dark:text-rose-400"
                >
                    Not enough balance.
                    <Link href="/my-deposits/create" class="underline">
                        Submit a deposit
                    </Link>
                    first.
                </p>
                <InputError :message="form.errors.charge_ids" />

                <DialogFooter>
                    <Button variant="outline" @click="isPayDialogOpen = false">
                        Cancel
                    </Button>
                    <Button
                        :disabled="form.processing || balanceAfterPayment < 0"
                        @click="payCharge"
                    >
                        Pay {{ formatMoney(payingCharge?.amount) }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
