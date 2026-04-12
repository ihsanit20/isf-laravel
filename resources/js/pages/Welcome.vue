<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { dashboard, login, register } from '@/routes';

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

    <div class="min-h-screen bg-slate-50 px-6 py-10 text-slate-900">
        <div class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-4xl flex-col">
            <header
                class="flex items-center justify-between border-b border-slate-200 pb-4"
            >
                <div class="flex items-center gap-3">
                    <AppLogoIcon class="h-12 object-contain" />

                    <p class="font-bold text-slate-700">
                        Al-Ihsan Savings Fund
                    </p>
                </div>

                <nav class="flex items-center gap-3 text-sm">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-md bg-slate-900 px-4 py-2 text-white"
                    >
                        Dashboard
                    </Link>

                    <template v-else>
                        <Link
                            :href="login()"
                            class="rounded-md border border-slate-300 px-4 py-2"
                        >
                            Log in
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="register()"
                            class="rounded-md bg-slate-900 px-4 py-2 text-white"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="flex flex-1 items-center">
                <section
                    class="w-full rounded-2xl bg-white p-8 shadow-sm ring-1 ring-slate-200 md:p-12"
                >
                    <p class="text-sm font-medium text-slate-500">
                        Group savings platform
                    </p>
                    <h1
                        class="mt-3 text-3xl font-semibold tracking-tight md:text-4xl"
                    >
                        Savings records, deposit tracking, and member statements
                        in one place.
                    </h1>
                    <p
                        class="mt-4 max-w-2xl text-base leading-7 text-slate-600"
                    >
                        ISF gives members and administrators a clear view of
                        balances, deposit history, and account activity.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <Link
                            v-if="$page.props.auth.user"
                            :href="dashboard()"
                            class="rounded-md bg-slate-900 px-5 py-3 text-sm font-medium text-white"
                        >
                            Go to dashboard
                        </Link>

                        <template v-else>
                            <Link
                                :href="login()"
                                class="rounded-md bg-slate-900 px-5 py-3 text-sm font-medium text-white"
                            >
                                Log in
                            </Link>

                            <Link
                                v-if="canRegister"
                                :href="register()"
                                class="rounded-md border border-slate-300 px-5 py-3 text-sm font-medium"
                            >
                                Create account
                            </Link>
                        </template>
                    </div>

                    <div
                        class="mt-10 grid gap-4 border-t border-slate-200 pt-6 text-sm text-slate-600 md:grid-cols-3"
                    >
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="font-medium text-slate-900">
                                Savings tracking
                            </p>
                            <p class="mt-1">
                                Review deposits and current balances with
                                clarity.
                            </p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="font-medium text-slate-900">
                                Verification status
                            </p>
                            <p class="mt-1">
                                Follow pending and approved deposits without
                                ambiguity.
                            </p>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-4">
                            <p class="font-medium text-slate-900">
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
