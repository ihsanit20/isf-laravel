<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { UserRole } from '@/types';

type EditableUser = {
    id: number;
    name: string;
    email: string;
    role: UserRole;
};

type Props = {
    mode: 'create' | 'edit';
    assignableRoles: UserRole[];
    user?: EditableUser | null;
};

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen', { default: false });

const isEditing = computed(() => props.mode === 'edit' && !!props.user);

const form = useForm<{
    name: string;
    email: string;
    role: UserRole;
    password: string;
    password_confirmation: string;
}>({
    name: '',
    email: '',
    role: props.assignableRoles[0] ?? 'member',
    password: '',
    password_confirmation: '',
});

const roleLabel = (role: UserRole): string =>
    role.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const resetFormState = () => {
    const values =
        isEditing.value && props.user
            ? {
                  name: props.user.name,
                  email: props.user.email,
                  role: props.user.role,
                  password: '',
                  password_confirmation: '',
              }
            : {
                  name: '',
                  email: '',
                  role: props.assignableRoles[0] ?? 'member',
                  password: '',
                  password_confirmation: '',
              };

    form.defaults(values);
    form.reset();
    form.clearErrors();
};

const closeDialog = () => {
    isOpen.value = false;
    resetFormState();
};

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeDialog();
        },
    };

    if (isEditing.value && props.user) {
        form.put(`/admin/users/${props.user.id}`, options);

        return;
    }

    form.post('/admin/users', options);
};

watch(
    () => [
        isOpen.value,
        props.mode,
        props.user?.id,
        props.assignableRoles.join(','),
    ],
    ([open]) => {
        if (open) {
            resetFormState();
        }
    },
    { immediate: true },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? 'Edit User' : 'Add User' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        isEditing
                            ? 'Update the selected user details and role assignment.'
                            : 'Create a new user account and assign an allowed role.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-2">
                    <Label for="user-name">Name</Label>
                    <Input
                        id="user-name"
                        v-model="form.name"
                        placeholder="Full name"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-email">Email</Label>
                    <Input
                        id="user-email"
                        v-model="form.email"
                        type="email"
                        placeholder="Email address"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-role">Role</Label>
                    <Select v-model="form.role">
                        <SelectTrigger id="user-role" class="w-full">
                            <SelectValue placeholder="Select a role" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="role in assignableRoles"
                                :key="role"
                                :value="role"
                            >
                                {{ roleLabel(role) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.role" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-password">
                        {{ isEditing ? 'New Password' : 'Password' }}
                    </Label>
                    <Input
                        id="user-password"
                        v-model="form.password"
                        type="password"
                        placeholder="Password"
                    />
                    <p v-if="isEditing" class="text-xs text-muted-foreground">
                        Leave this blank to keep the current password unchanged.
                    </p>
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="user-password-confirmation"
                        >Confirm Password</Label
                    >
                    <Input
                        id="user-password-confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        placeholder="Confirm password"
                    />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        @click="closeDialog"
                    >
                        Cancel
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ isEditing ? 'Save Changes' : 'Add User' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
