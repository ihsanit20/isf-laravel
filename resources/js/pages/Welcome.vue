<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { about, dashboard, login, register, terms } from '@/routes';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);
</script>

<template>
    <Head title="Home" />

    <div class="min-h-screen bg-background px-6 py-10 text-foreground">
        <div class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-4xl flex-col">
            <header
                class="flex flex-col gap-4 border-b border-border pb-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-center gap-3">
                    <AppLogoIcon class="h-12 object-contain" />

                    <p class="font-bold text-foreground">
                        Al-Ihsan Savings Fund
                    </p>
                </div>

                <nav
                    class="flex w-full flex-wrap items-center gap-2 text-sm md:w-auto md:justify-end"
                >
                    <Link
                        :href="about()"
                        class="rounded-md px-3 py-2 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                    >
                        About ISF
                    </Link>

                    <Link
                        :href="terms()"
                        class="rounded-md px-3 py-2 text-muted-foreground transition hover:bg-accent hover:text-accent-foreground"
                    >
                        Terms & Conditions
                    </Link>

                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-md bg-primary px-4 py-2 text-primary-foreground"
                    >
                        Dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-md border border-border bg-background px-4 py-2 transition hover:bg-accent hover:text-accent-foreground"
                        >
                            Log in
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-md bg-primary px-4 py-2 text-primary-foreground"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="flex flex-1 items-center">
                <section
                    class="w-full rounded-2xl bg-card p-8 text-card-foreground shadow-sm ring-1 ring-border md:p-12"
                >
                    <p class="text-sm font-medium text-muted-foreground">
                        Group savings platform
                    </p>
                    <h1
                        class="mt-3 text-3xl font-semibold tracking-tight md:text-4xl"
                    >
                        Savings records, deposit tracking, and member statements
                        in one place.
                    </h1>
                    <p
                        class="mt-4 max-w-2xl text-base leading-7 text-muted-foreground"
                    >
                        ISF gives members and administrators a clear view of
                        balances, deposit history, and account activity.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="rounded-md bg-primary px-5 py-3 text-sm font-medium text-primary-foreground"
                        >
                            Go to dashboard
                        </Link>

                        <template v-else>
                            <Link
                                :href="login()"
                                class="rounded-md bg-primary px-5 py-3 text-sm font-medium text-primary-foreground"
                            >
                                Log in
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="rounded-md border border-border bg-background px-5 py-3 text-sm font-medium transition hover:bg-accent hover:text-accent-foreground"
                            >
                                Create account
                            </Link>
                        </template>
                    </div>

                    <div
                        class="mt-10 grid gap-4 border-t border-border pt-6 text-sm text-muted-foreground md:grid-cols-3"
                    >
                        <div class="rounded-xl bg-muted/50 p-4">
                            <p class="font-medium text-foreground">
                                Savings tracking
                            </p>
                            <p class="mt-1">
                                Review deposits and current balances with
                                clarity.
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/50 p-4">
                            <p class="font-medium text-foreground">
                                Verification status
                            </p>
                            <p class="mt-1">
                                Follow pending and approved deposits without
                                ambiguity.
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/50 p-4">
                            <p class="font-medium text-foreground">
                                Member access
                            </p>
                            <p class="mt-1">
                                Sign in and review statements without
                                unnecessary steps.
                            </p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>
