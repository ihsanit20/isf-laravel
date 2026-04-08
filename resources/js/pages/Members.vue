<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CalendarDays,
    CircleAlert,
    Clock3,
    Phone,
    Plus,
    UserRound,
    WalletCards,
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type MemberStatus = 'pending' | 'approved' | 'rejected' | 'exited';
type RelationshipOption = 'self' | 'spouse' | 'child' | 'parent' | 'other';

type MemberItem = {
    id: number;
    full_name: string;
    phone: string | null;
    relationship_to_user: RelationshipOption;
    units: number;
    status: MemberStatus;
    rejection_note: string | null;
    applied_at: string | null;
    approved_at: string | null;
};

type Props = {
    members: MemberItem[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Membership',
                href: '/my-membership',
            },
        ],
    },
});

defineProps<Props>();

const relationshipLabel = (value: RelationshipOption): string =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const statusLabel = (value: MemberStatus): string =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const statusVariant = (
    status: MemberStatus,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'approved') {
        return 'default';
    }

    if (status === 'rejected') {
        return 'destructive';
    }

    if (status === 'exited') {
        return 'outline';
    }

    return 'secondary';
};

const statusSurfaceClass = (status: MemberStatus): string => {
    if (status === 'approved') {
        return 'border-emerald-200/80 bg-linear-to-br from-emerald-50 via-background to-background';
    }

    if (status === 'rejected') {
        return 'border-rose-200/80 bg-linear-to-br from-rose-50 via-background to-background';
    }

    if (status === 'exited') {
        return 'border-slate-200/80 bg-linear-to-br from-slate-100 via-background to-background';
    }

    return 'border-amber-200/80 bg-linear-to-br from-amber-50 via-background to-background';
};
</script>

<template>
    <Head title="My Membership" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-3xl border border-sidebar-border/70 bg-background p-6 shadow-sm"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="max-w-2xl">
                    <h1 class="mt-3 text-3xl font-semibold tracking-tight">
                        My Membership
                    </h1>
                    <p
                        class="mt-2 max-w-xl text-sm leading-6 text-muted-foreground"
                    >
                        Review all membership applications and their current
                        status in one place.
                    </p>
                </div>

                <Button as-child class="shrink-0">
                    <Link href="/my-membership/create">
                        <Plus class="size-4" />
                        Apply for Membership
                    </Link>
                </Button>
            </div>
        </section>

        <section
            v-if="members.length > 0"
            class="grid gap-4 xl:grid-cols-2 2xl:grid-cols-3"
        >
            <article
                v-for="member in members"
                :key="member.id"
                class="rounded-[26px] border p-5 shadow-sm transition-transform duration-200 hover:-translate-y-0.5"
                :class="statusSurfaceClass(member.status)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Application #{{ member.id }}
                        </p>
                        <h2
                            class="mt-2 text-xl font-semibold tracking-tight text-foreground"
                        >
                            {{ member.full_name }}
                        </h2>
                    </div>

                    <Badge :variant="statusVariant(member.status)">
                        {{ statusLabel(member.status) }}
                    </Badge>
                </div>

                <div class="mt-5 grid gap-3 text-sm">
                    <div
                        class="flex items-center gap-3 rounded-2xl bg-background/75 px-3 py-3"
                    >
                        <UserRound class="size-4 text-muted-foreground" />
                        <div>
                            <p class="text-xs text-muted-foreground">
                                Relationship
                            </p>
                            <p class="font-medium text-foreground">
                                {{
                                    relationshipLabel(
                                        member.relationship_to_user,
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-3 rounded-2xl bg-background/75 px-3 py-3"
                    >
                        <WalletCards class="size-4 text-muted-foreground" />
                        <div>
                            <p class="text-xs text-muted-foreground">Units</p>
                            <p class="font-medium text-foreground">
                                {{ member.units }} unit{{
                                    member.units > 1 ? 's' : ''
                                }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="flex items-center gap-3 rounded-2xl bg-background/75 px-3 py-3"
                    >
                        <Phone class="size-4 text-muted-foreground" />
                        <div>
                            <p class="text-xs text-muted-foreground">Phone</p>
                            <p class="font-medium text-foreground">
                                {{ member.phone || 'Not set' }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <div class="rounded-2xl bg-background/75 px-3 py-3">
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <CalendarDays class="size-4" />
                                Applied At
                            </div>
                            <p class="mt-2 font-medium text-foreground">
                                {{ member.applied_at || 'Not available' }}
                            </p>
                        </div>

                        <div class="rounded-2xl bg-background/75 px-3 py-3">
                            <div
                                class="flex items-center gap-2 text-xs text-muted-foreground"
                            >
                                <Clock3 class="size-4" />
                                Approval
                            </div>
                            <p class="mt-2 font-medium text-foreground">
                                {{ member.approved_at || 'Pending review' }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-dashed border-border/80 bg-background/70 px-3 py-3"
                    >
                        <div
                            class="flex items-center gap-2 text-xs text-muted-foreground"
                        >
                            <CircleAlert class="size-4" />
                            Review Note
                        </div>
                        <p class="mt-2 text-sm leading-6 text-foreground">
                            {{
                                member.rejection_note ||
                                (member.approved_at
                                    ? 'Approved by an administrator.'
                                    : 'No review note available.')
                            }}
                        </p>
                    </div>
                </div>
            </article>
        </section>

        <section
            v-else
            class="rounded-[28px] border border-dashed border-sidebar-border/80 bg-background p-10 text-center shadow-sm"
        >
            <div class="mx-auto max-w-md">
                <p
                    class="text-sm font-medium tracking-[0.2em] text-muted-foreground uppercase"
                >
                    No Membership Yet
                </p>
                <h2 class="mt-3 text-2xl font-semibold tracking-tight">
                    No membership applications found
                </h2>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">
                    Submit a new application to create a membership record for
                    yourself or an eligible family member.
                </p>
                <Button as-child class="mt-6">
                    <Link href="/my-membership/create">
                        <Plus class="size-4" />
                        Open Membership Form
                    </Link>
                </Button>
            </div>
        </section>
    </div>
</template>
