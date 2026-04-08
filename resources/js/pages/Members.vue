<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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

type MemberStatus = 'pending' | 'approved' | 'rejected' | 'inactive';
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
    relationshipOptions: RelationshipOption[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'My Members',
                href: '/members',
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
}>({
    full_name: '',
    phone: '',
    relationship_to_user: 'self',
    units: 1,
});

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

    if (status === 'inactive') {
        return 'outline';
    }

    return 'secondary';
};

const submit = () => {
    form.post('/members', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.relationship_to_user = 'self';
            form.units = 1;
        },
    });
};
</script>

<template>
    <Head title="My Members" />

    <div class="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
        <section
            class="rounded-xl border border-sidebar-border/70 bg-background p-6 shadow-sm dark:border-sidebar-border"
        >
            <div class="max-w-2xl">
                <h1 class="text-2xl font-semibold tracking-tight">
                    My Members
                </h1>
                <p class="mt-2 text-sm text-muted-foreground">
                    নিজের জন্য বা family member-এর জন্য membership application
                    এখান থেকে submit করুন।
                </p>
            </div>

            <form
                class="mt-6 grid gap-4 md:grid-cols-2"
                @submit.prevent="submit"
            >
                <div class="grid gap-2 md:col-span-2">
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
                    <Label for="member-relationship">Relationship</Label>
                    <Select v-model="form.relationship_to_user">
                        <SelectTrigger id="member-relationship" class="w-full">
                            <SelectValue placeholder="Select relationship" />
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
                    <InputError :message="form.errors.relationship_to_user" />
                </div>

                <div class="grid gap-2 md:col-span-2 md:max-w-xs">
                    <Label for="member-units">Units</Label>
                    <Input
                        id="member-units"
                        v-model.number="form.units"
                        type="number"
                        min="1"
                    />
                    <p class="text-xs text-muted-foreground">
                        Monthly savings will be calculated as units × 1000 BDT.
                    </p>
                    <InputError :message="form.errors.units" />
                </div>

                <div class="md:col-span-2">
                    <Button type="submit" :disabled="form.processing">
                        Submit Membership Application
                    </Button>
                </div>
            </form>
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
                            <th class="px-4 py-3 font-medium">Phone</th>
                            <th class="px-4 py-3 font-medium">Relationship</th>
                            <th class="px-4 py-3 font-medium">Units</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Applied At</th>
                            <th class="px-4 py-3 font-medium">Note</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/70">
                        <tr v-for="member in members" :key="member.id">
                            <td class="px-4 py-3 font-medium">
                                {{ member.full_name }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ member.phone || 'Not set' }}
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
                                    {{ statusLabel(member.status) }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{ member.applied_at || 'Pending' }}
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">
                                {{
                                    member.rejection_note ||
                                    (member.approved_at ? 'Approved' : '-')
                                }}
                            </td>
                        </tr>
                        <tr v-if="members.length === 0">
                            <td
                                colspan="7"
                                class="px-4 py-8 text-center text-muted-foreground"
                            >
                                No member applications submitted yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</template>
