<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { about, dashboard, home, login, register, terms } from '@/routes';

const page = usePage();

const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const canRegister = computed(() => Boolean(page.props.canRegister));
</script>

<template>
    <div class="min-h-screen bg-background px-6 py-10 text-foreground">
        <div class="mx-auto flex min-h-[calc(100vh-5rem)] max-w-4xl flex-col">
            <header
                class="flex flex-col gap-4 border-b border-border pb-4 md:flex-row md:items-center md:justify-between"
            >
                <Link :href="home()" class="flex items-center gap-3">
                    <AppLogoIcon class="h-12 w-auto object-contain" />

                    <p class="font-bold text-foreground">
                        Al-Ihsan Savings Fund
                    </p>
                </Link>

                <nav class="flex flex-wrap items-center gap-3 text-sm">
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
                        v-if="isAuthenticated"
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

            <main class="flex flex-1 items-start py-8 md:py-10">
                <section
                    class="w-full rounded-2xl bg-card p-8 text-card-foreground shadow-sm ring-1 ring-border md:p-12"
                >
                    <slot />
                </section>
            </main>
        </div>
    </div>
</template>
