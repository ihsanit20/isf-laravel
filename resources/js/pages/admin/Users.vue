<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, SquarePen } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import UserFormDialog from '@/components/admin/UserFormDialog.vue';
import { Button } from '@/components/ui/button';
import type { UserRole } from '@/types';

type AdminUser = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    role: UserRole;
    created_at: string;
    can_edit: boolean;
};

type Props = {
    users: AdminUser[];
    assignableRoles: UserRole[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'User List',
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

const roleLabel = (role: UserRole): string =>
    role.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const openEditDialog = (user: AdminUser) => {
    if (!user.can_edit) {
        return;
    }

    selectedUser.value = user;
    isEditDialogOpen.value = true;
};
</script>

<template>
    <Head title="User List" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        User List
                    </h1>
                    <p class="mt-2 text-sm text-muted-foreground">
                        Admin account গুলো থেকে system user management দেখার
                        জন্য এই তালিকাটি ব্যবহার করুন।
                    </p>
                </div>

                <Button class="shrink-0" @click="isCreateDialogOpen = true">
                    <Plus class="size-4" />
                    Add User
                </Button>
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
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Phone</th>
                            <th class="px-4 py-3 font-medium">Role</th>
                            <th class="px-4 py-3 font-medium">Joined At</th>
                            <th class="px-4 py-3 font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="user in users" :key="user.id">
                            <td class="px-4 py-3 font-medium">
                                {{ user.name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ user.email }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ user.phone || 'Not set' }}
                            </td>
                            <td class="px-4 py-3 capitalize">
                                {{ roleLabel(user.role) }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ user.created_at }}
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    v-if="user.can_edit"
                                    variant="outline"
                                    size="sm"
                                    @click="openEditDialog(user)"
                                >
                                    <SquarePen class="size-4" />
                                    Edit
                                </Button>
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                >
                                    Restricted
                                </span>
                            </td>
                        </tr>
                        <tr v-if="users.length === 0">
                            <td
                                colspan="6"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No users found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

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
