<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Terms & Conditions',
                href: '/terms-and-conditions',
            },
        ],
    },
});

const terms = [
    'Every financial deposit must be recorded under a specific member, not under a user account.',
    'The registration fee for each member is BDT 100.',
    'The amount for each unit is BDT 1,000.',
    'Interested participants may take one or more units based on their preference.',
    'The fund begins with a 3-month trial period focused only on savings activity.',
    'During the trial period, no investment activity or Qard Hasan disbursement will be provided.',
    'Every submitted deposit is subject to admin review before approval.',
    'All fund money should be kept in a joint bank account and transactions should be completed through banking channels.',
    'Approved financial records must not be deleted directly; if needed, changes should be handled through adjustments or follow-up corrections.',
    'Members should regularly review the balance, deposit history, and status shown in their own dashboard.',
    'A single user account may manage multiple member profiles, but each member record must remain separate.',
    'At least two responsible members should oversee fund accounting and supervision.',
    'If a member chooses to exit, their eligible amount should be settled within 30 to 90 days.',
    'Incorrect information, incomplete allocations, or inconsistent deposits may be returned or held for correction before approval.',
];
</script>

<template>
    <Head title="Terms & Conditions" />

    <div class="space-y-6 p-4">
        <Heading
            title="Terms & Conditions"
            description="The basic policies and conditions that apply to members and administrators using the fund platform."
        />

        <Card>
            <CardHeader>
                <CardTitle>Applicability</CardTitle>
                <CardDescription>
                    These terms apply to all members and administrative users of
                    the ISF platform.
                </CardDescription>
            </CardHeader>
            <CardContent
                class="space-y-4 text-sm leading-6 text-muted-foreground"
            >
                <p>
                    Information, deposits, verification statuses, and
                    member-based ledger records shown on the platform are
                    treated as part of the official fund management process.
                </p>
                <p>
                    To maintain transparency and auditability, all users must
                    provide accurate information and use the platform
                    responsibly.
                </p>
                <p>
                    The fund is intended to remain a savings-first initiative,
                    with any future expansion handled only through shared member
                    agreement and a separate policy where needed.
                </p>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Key Rules</CardTitle>
                <CardDescription>
                    The conditions required to maintain member-based financial
                    discipline, verification, and transparency.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ol class="space-y-4 text-sm leading-6 text-muted-foreground">
                    <li
                        v-for="(term, index) in terms"
                        :key="term"
                        class="flex gap-3"
                    >
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary"
                        >
                            {{ index + 1 }}
                        </span>
                        <span>{{ term }}</span>
                    </li>
                </ol>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Responsibility</CardTitle>
                <CardDescription>
                    Users are responsible for selecting the correct member,
                    providing accurate deposit information, and keeping required
                    records when needed.
                </CardDescription>
            </CardHeader>
            <CardContent
                class="space-y-4 text-sm leading-6 text-muted-foreground"
            >
                <p>
                    The administration may re-check deposit or member-related
                    information when necessary. If inconsistencies are found,
                    the record may be temporarily held for review.
                </p>
                <p>
                    Members who change jobs, leave the organization, or move
                    abroad are not automatically removed from the fund; they may
                    continue participation under the rules or request an exit.
                </p>
                <p>
                    These platform rules may be updated when required, and users
                    will be informed of major changes when applicable.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
