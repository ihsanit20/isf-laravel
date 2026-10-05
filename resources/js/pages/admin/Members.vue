<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Check, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import MemberReviewDialog from '@/components/admin/MemberReviewDialog.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import StatusBadge from '@/components/shared/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableEmpty,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { titleCase } from '@/lib/format';

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
                title: 'Members',
                href: '/admin/members',
            },
        ],
    },
});

const props = defineProps<Props>();

const isApproveDialogOpen = ref(false);
const isRejectDialogOpen = ref(false);
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

const statusFilter = ref<'all' | MemberStatus>('all');

const filters = computed(() =>
    (['all', 'pending', 'approved', 'rejected', 'exited'] as const).map(
        (status) => ({
            value: status,
            count:
                status === 'all'
                    ? props.members.length
                    : props.members.filter((member) => member.status === status)
                          .length,
        }),
    ),
);

const visibleMembers = computed(() =>
    statusFilter.value === 'all'
        ? props.members
        : props.members.filter(
              (member) => member.status === statusFilter.value,
          ),
);

const openApproveDialog = (member: AdminMember) => {
    selectedMember.value = member;
    isApproveDialogOpen.value = true;
};

const openRejectDialog = (member: AdminMember) => {
    selectedMember.value = member;
    isRejectDialogOpen.value = true;
};
</script>

<template>
    <Head title="Members" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Members"
            description="Membership applications from all users. Approve a member, then the user pays the registration fee to activate them."
        />

        <Tabs v-model="statusFilter">
            <TabsList class="h-auto flex-wrap">
                <TabsTrigger
                    v-for="filter in filters"
                    :key="filter.value"
                    :value="filter.value"
                >
                    {{ titleCase(filter.value) }}
                    <span class="text-xs text-muted-foreground">
                        {{ filter.count }}
                    </span>
                </TabsTrigger>
            </TabsList>
        </Tabs>

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12 pl-4">SL</TableHead>
                        <TableHead>Member</TableHead>
                        <TableHead>Managed by</TableHead>
                        <TableHead class="text-right">Units</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Applied</TableHead>
                        <TableHead>Reviewed by</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow
                        v-for="(member, index) in visibleMembers"
                        :key="member.id"
                    >
                        <TableCell
                            class="pl-4 text-muted-foreground tabular-nums"
                            >{{ index + 1 }}</TableCell
                        >
                        <TableCell>
                            <p class="font-medium">{{ member.full_name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ titleCase(member.relationship_to_user) }}
                                <template v-if="member.phone">
                                    · {{ member.phone }}
                                </template>
                            </p>
                        </TableCell>
                        <TableCell>
                            <p>
                                {{ member.manager.name || 'Unknown account' }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ member.manager.email || '—' }}
                            </p>
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{ member.units }}
                        </TableCell>
                        <TableCell class="max-w-xs whitespace-normal">
                            <StatusBadge :status="member.status" />
                            <p
                                v-if="member.rejection_note"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ member.rejection_note }}
                            </p>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ member.applied_at || '—' }}
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ member.approver || '—' }}
                        </TableCell>
                        <TableCell class="pr-4">
                            <div
                                v-if="member.status === 'pending'"
                                class="flex justify-end gap-2"
                            >
                                <Button
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
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="visibleMembers.length === 0" :colspan="8">
                        No members here.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

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
    </div>
</template>
