<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/shared/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
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

type Props = {
    relationshipOptions: RelationshipOption[];
};

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Members',
                href: '/my-membership',
            },
            {
                title: 'Add Member',
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
}>({
    full_name: '',
    phone: '',
    relationship_to_user: 'self',
    units: 1,
});

const relationshipLabel = (value: RelationshipOption): string =>
    value.replace('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const steps = [
    {
        title: 'Admin reviews',
        description:
            'An admin approves the application and adds the registration fee.',
    },
    {
        title: 'Pay the registration fee',
        description:
            'Pay it from your balance on the Members page. This activates the member.',
    },
    {
        title: 'Invest',
        description:
            'An active member can invest in open fund cycles from the Investments page.',
    },
];

const submit = () => {
    form.post('/my-membership', {
        preserveScroll: true,
        forceFormData: true,
    });
};
</script>

<template>
    <Head title="Add Member" />

    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <PageHeader
            title="Add member"
            description="Apply for a membership for yourself or a family member. Each member invests in fund cycles separately."
        >
            <template #actions>
                <Button as-child variant="outline">
                    <Link href="/my-membership">
                        <ArrowLeft class="size-4" />
                        Members
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
            <Card>
                <CardContent>
                    <form @submit.prevent="submit">
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
                                            {{
                                                relationshipLabel(relationship)
                                            }}
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
                                    Monthly savings are calculated as units ×
                                    1000 BDT.
                                </p>
                                <InputError :message="form.errors.units" />
                            </div>

                            <div class="pt-2">
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                >
                                    Submit application
                                </Button>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card class="h-fit gap-4 bg-muted/30">
                <CardHeader>
                    <CardTitle class="text-base">What happens next</CardTitle>
                </CardHeader>
                <CardContent>
                    <ol class="grid gap-4 text-sm">
                        <li
                            v-for="(step, index) in steps"
                            :key="step.title"
                            class="flex gap-3"
                        >
                            <span
                                class="flex size-6 shrink-0 items-center justify-center rounded-full border bg-background text-xs font-medium"
                            >
                                {{ index + 1 }}
                            </span>
                            <div>
                                <p class="font-medium">{{ step.title }}</p>
                                <p class="text-muted-foreground">
                                    {{ step.description }}
                                </p>
                            </div>
                        </li>
                    </ol>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
