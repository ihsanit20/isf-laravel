<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, SquarePen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UserFormDialog from '@/components/admin/UserFormDialog.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
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
import { formatMoney, titleCase } from '@/lib/format';
import type { UserRole } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: UserRole;
    can_edit: boolean;
    total_verified_deposit_amount: number;
    member_total_allocated_amount: number;
    available_balance: number;
};

type Props = {
    users: AdminUser[];
    assignableRoles: UserRole[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Users',
                href: '/admin/users',
            },
        ],
    },
});

const props = defineProps<Props>();

const isCreateDialogOpen = ref(false);
const isEditDialogOpen = ref(false);
const selectedUser = ref<AdminUser | null>(null);

const editableUser = computed(() => {
    if (!selectedUser.value) {
        return null;
    }

    return {
        id: selectedUser.value.id,
        name: selectedUser.value.name,
        email: selectedUser.value.email,
        phone: selectedUser.value.phone,
        role: selectedUser.value.role,
    };
});

const openEditDialog = (user: AdminUser) => {
    if (!user.can_edit) {
        return;
    }

    selectedUser.value = user;
    isEditDialogOpen.value = true;
};
</script>

<template>
    <Head title="Users" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Users"
            description="Accounts, their roles and where each user’s money stands."
        >
            <template #actions>
                <Button @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    Add user
                </Button>
            </template>
        </PageHeader>

        <Card class="py-0">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead class="w-12 pl-4">SL</TableHead>
                        <TableHead>Name</TableHead>
                        <TableHead>Phone</TableHead>
                        <TableHead class="text-right">
                            Verified deposits
                        </TableHead>
                        <TableHead class="text-right">Invested</TableHead>
                        <TableHead class="text-right">Available</TableHead>
                        <TableHead>Role</TableHead>
                        <TableHead class="pr-4 text-right" />
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="(user, index) in users" :key="user.id">
                        <TableCell
                            class="pl-4 text-muted-foreground tabular-nums"
                            >{{ index + 1 }}</TableCell
                        >
                        <TableCell>
                            <p class="font-medium">{{ user.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ user.email }}
                            </p>
                        </TableCell>
                        <TableCell class="text-muted-foreground">
                            {{ user.phone || '—' }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{
                                formatMoney(user.total_verified_deposit_amount)
                            }}
                        </TableCell>
                        <TableCell class="text-right tabular-nums">
                            {{
                                formatMoney(user.member_total_allocated_amount)
                            }}
                        </TableCell>
                        <TableCell
                            class="text-right font-medium tabular-nums"
                            :class="
                                user.available_balance < 0
                                    ? 'text-rose-600 dark:text-rose-400'
                                    : ''
                            "
                        >
                            {{ formatMoney(user.available_balance) }}
                        </TableCell>
                        <TableCell>
                            <span
                                class="rounded-md bg-muted px-2 py-0.5 text-xs font-medium"
                            >
                                {{ titleCase(user.role) }}
                            </span>
                        </TableCell>
                        <TableCell class="pr-4 text-right">
                            <Button
                                v-if="user.can_edit"
                                variant="outline"
                                size="sm"
                                @click="openEditDialog(user)"
                            >
                                <SquarePen class="size-4" />
                                Edit
                            </Button>
                            <span v-else class="text-xs text-muted-foreground">
                                Restricted
                            </span>
                        </TableCell>
                    </TableRow>
                    <TableEmpty v-if="users.length === 0" :colspan="8">
                        No users found.
                    </TableEmpty>
                </TableBody>
            </Table>
        </Card>

        <UserFormDialog
            v-model:isOpen="isCreateDialogOpen"
            mode="create"
            :assignable-roles="props.assignableRoles"
        />

        <UserFormDialog
            v-model:isOpen="isEditDialogOpen"
            mode="edit"
            :assignable-roles="props.assignableRoles"
            :user="editableUser"
        />
    </div>
</template>
