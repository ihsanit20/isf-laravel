<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, LogOut, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MemberReviewDialog from '@/components/admin/MemberReviewDialog.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type MemberStatus = 'pending' | 'approved' | 'rejected' | 'exited';
type RelationshipOption = 'self' | 'spouse' | 'child' | 'parent' | 'other';

type AdminMember = {
    id: number;
    full_name: string;
    phone: string | null;
    relationship_to_user: RelationshipOption;
    units: number;
    status: MemberStatus;
    rejection_note: string | null;
    applied_at: string | null;
    approved_at: string | null;
    manager: {
        name: string | null;
        email: string | null;
    };
    approver: string | null;
};

type Props = {
    members: AdminMember[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Member List',
                href: '/admin/members',
            },
        ],
    },
});

defineProps<Props>();

const isApproveDialogOpen = ref(false);
const isRejectDialogOpen = ref(false);
const isExitDialogOpen = ref(false);
const selectedMember = ref<AdminMember | null>(null);

const reviewableMember = computed(() => {
    if (!selectedMember.value) {
        return null;
    }

    return {
        id: selectedMember.value.id,
        full_name: selectedMember.value.full_name,
        status: selectedMember.value.status,
    };
});

const relationshipLabel = (value: string): string =>
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

const openApproveDialog = (member: AdminMember) => {
    selectedMember.value = member;
    isApproveDialogOpen.value = true;
};

const openRejectDialog = (member: AdminMember) => {
    selectedMember.value = member;
    isRejectDialogOpen.value = true;
};

const openExitDialog = (member: AdminMember) => {
    selectedMember.value = member;
    isExitDialogOpen.value = true;
};
</script>

<template>
    <Head title="Member List" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="max-w-2xl">
                <h1 class="text-2xl font-semibold tracking-tight">
                    Member List
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    Review submitted membership applications and record approval
                    decisions.
                </p>
            </div>
        </section>

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-background shadow-sm dark:border-sidebar-border"
        >
            <div class="overflow-x-auto">
                <table
                    class="min-w-full divide-y divide-sidebar-border/70 text-sm"
                >
                    <thead class="bg-muted/40 text-left">
                        <tr>
                            <th class="px-4 py-3 font-medium">Member</th>
                            <th class="px-4 py-3 font-medium">Managed By</th>
                            <th class="px-4 py-3 font-medium">Relationship</th>
                            <th class="px-4 py-3 font-medium">Units</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Applied At</th>
                            <th class="px-4 py-3 font-medium">Reviewed By</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="member in members" :key="member.id">
                            <td class="px-4 py-3">
                                <div class="font-medium">
                                    {{ member.full_name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ member.phone || 'Phone not provided' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                <div>
                                    {{
                                        member.manager.name || 'Unknown account'
                                    }}
                                </div>
                                <div class="text-xs">
                                    {{ member.manager.email || '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{
                                    relationshipLabel(
                                        member.relationship_to_user,
                                    )
                                }}
                            </td>
                            <td class="px-4 py-3">{{ member.units }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="statusVariant(member.status)">
                                    {{ relationshipLabel(member.status) }}
                                </Badge>
                                <p
                                    v-if="member.rejection_note"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ member.rejection_note }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ member.applied_at || '-' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ member.approver || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <div
                                    v-if="member.status === 'pending'"
                                    class="flex flex-wrap gap-2"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openApproveDialog(member)"
                                    >
                                        <Check class="size-4" />
                                        Approve
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openRejectDialog(member)"
                                    >
                                        <X class="size-4" />
                                        Reject
                                    </Button>
                                </div>
                                <div
                                    v-else-if="member.status === 'approved'"
                                    class="flex flex-wrap gap-2"
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openExitDialog(member)"
                                    >
                                        <LogOut class="size-4" />
                                        Exit
                                    </Button>
                                </div>
                                <span
                                    v-else
                                    class="text-xs text-muted-foreground"
                                >
                                    Reviewed
                                </span>
                            </td>
                        </tr>
                        <tr v-if="members.length === 0">
                            <td
                                colspan="8"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No members found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <MemberReviewDialog
            v-model:isOpen="isApproveDialogOpen"
            mode="approve"
            :member="reviewableMember"
        />

        <MemberReviewDialog
            v-model:isOpen="isRejectDialogOpen"
            mode="reject"
            :member="reviewableMember"
        />

        <MemberReviewDialog
            v-model:isOpen="isExitDialogOpen"
            mode="exit"
            :member="reviewableMember"
        />
    </div>
</template>
