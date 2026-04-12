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

const objectives = [
    'Collect member-based savings, verify deposits, and maintain transparent financial records.',
    'Give each member clear access to their own deposits, balances, and ledger history.',
    'Support family-based or multi-member management in a simple and reliable way.',
    'Keep administrative workflows concise, traceable, and low risk.',
];

const principles = [
    'Participation in the fund is voluntary and based on mutual trust and cooperation among colleagues.',
    'All transactions are intended to move through banking channels so every deposit remains secure and verifiable.',
    'A software-based dashboard helps members track deposits, balances, and transaction status clearly.',
    'Fund operations are expected to remain transparent, disciplined, and accountable to participating members.',
];
</script>

<template>
    <Head title="About Us" />

    <div class="space-y-6">
        <Heading
            title="About Us"
            description="A quick overview of Al-Ihsan Savings Fund, including our mission, vision, and core objectives."
        />

        <Card>
            <CardHeader>
                <CardTitle>Who We Are</CardTitle>
                <CardDescription>
                    Al-Ihsan Savings Fund is a voluntary group savings fund for
                    colleagues, built around trust, savings discipline,
                    cooperation, and long-term financial planning.
                </CardDescription>
            </CardHeader>
            <CardContent
                class="space-y-4 text-sm leading-6 text-muted-foreground"
            >
                <p>
                    The fund is designed to operate through a transparent,
                    disciplined, and responsible structure supported by bank-
                    based transactions, software-based record keeping, and the
                    shared understanding of participating members.
                </p>
                <p>
                    At this stage, the main focus is savings collection, deposit
                    verification, record keeping, and providing members with a
                    clear view of their financial activity.
                </p>
            </CardContent>
        </Card>

        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle>Mission</CardTitle>
                    <CardDescription>
                        Build member trust through secure and clearly managed
                        savings operations.
                    </CardDescription>
                </CardHeader>
                <CardContent class="text-sm leading-6 text-muted-foreground">
                    Encourage a savings culture among colleagues, create a
                    healthy environment of mutual support, and build an orderly
                    and transparent fund that can serve future financial needs.
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Vision</CardTitle>
                    <CardDescription>
                        A sustainable fund management structure built on
                        transparency, accountability, and member confidence.
                    </CardDescription>
                </CardHeader>
                <CardContent class="text-sm leading-6 text-muted-foreground">
                    Establish a reliable fund structure where every member can
                    verify their position easily and administrators can operate
                    with a clear audit trail and shared accountability.
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle>Operating Principles</CardTitle>
                <CardDescription>
                    The core ideas guiding how the fund is expected to operate.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul class="space-y-3 text-sm leading-6 text-muted-foreground">
                    <li
                        v-for="principle in principles"
                        :key="principle"
                        class="flex gap-3"
                    >
                        <span class="mt-2 h-2 w-2 rounded-full bg-primary" />
                        <span>{{ principle }}</span>
                    </li>
                </ul>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>Core Objectives</CardTitle>
                <CardDescription>
                    Keep fund operations simple, traceable, and member-friendly.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <ul class="space-y-3 text-sm leading-6 text-muted-foreground">
                    <li
                        v-for="objective in objectives"
                        :key="objective"
                        class="flex gap-3"
                    >
                        <span class="mt-2 h-2 w-2 rounded-full bg-primary" />
                        <span>{{ objective }}</span>
                    </li>
                </ul>
            </CardContent>
        </Card>
    </div>
</template>
