<script setup>
import { Head, Link } from "@inertiajs/vue3";

const quickStats = [
    {
        label: "সক্রিয় সদস্য প্রোফাইল",
        value: "01",
        detail: "একজন user থেকে একাধিক member পরিচালনা",
    },
    {
        label: "স্বচ্ছ জমা ট্র্যাকিং",
        value: "24/7",
        detail: "প্রতিটি জমা যাচাই ও status-visible flow",
    },
    {
        label: "অডিট-ফ্রেন্ডলি লেজার",
        value: "100%",
        detail: "approved entry edit না করে adjustment-first history",
    },
];

const featureCards = [
    {
        title: "সেভিংস কালেকশন সহজ",
        copy: "নির্ধারিত savings collection, deposit submission, আর verification flow একই জায়গা থেকে দেখা যাবে।",
    },
    {
        title: "প্রতিটি টাকা traceable",
        copy: "member-based ledger history থাকায় কার জমা, কোন status, কখন approve হয়েছে তা পরিষ্কার থাকবে।",
    },
    {
        title: "পরিবারভিত্তিক সদস্য ব্যবস্থাপনা",
        copy: "একটি login account থেকে একাধিক family membership দেখাশোনা করা যাবে।",
    },
];

const steps = [
    {
        title: "জমা সাবমিট",
        copy: "সদস্য জমার তথ্য পাঠাবে এবং pending status সঙ্গে সঙ্গে দেখা যাবে।",
    },
    {
        title: "অ্যাডমিন ভেরিফিকেশন",
        copy: "admin approval-এর পর ledger entry append হবে, পুরনো entry overwrite হবে না।",
    },
    {
        title: "স্টেটমেন্ট ও রিপোর্ট",
        copy: "member balance, dues, approved deposits, এবং recent activity সবাই পরিষ্কারভাবে দেখতে পারবে।",
    },
];

const trustSignals = [
    "Member-first financial records",
    "Simple approval workflow",
    "Low operational risk",
    "Transparent balances and statements",
];

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
});
</script>

<template>
    <Head title="ISF" />
    <div class="homepage-shell overflow-hidden">
        <div class="page-orb page-orb-left"></div>
        <div class="page-orb page-orb-right"></div>

        <div
            class="relative mx-auto flex min-h-screen w-full max-w-7xl flex-col px-6 pb-12 pt-6 sm:px-8 lg:px-10 lg:pb-20"
        >
            <header
                class="flex flex-col gap-6 rounded-[28px] border border-[var(--line)] bg-white/70 px-5 py-4 backdrop-blur md:flex-row md:items-center md:justify-between md:px-6"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[var(--ink)] text-sm font-bold tracking-[0.24em] text-white shadow-[0_18px_40px_rgba(16,53,42,0.24)]"
                    >
                        ISF
                    </div>

                    <div>
                        <p
                            class="font-display text-sm font-semibold uppercase tracking-[0.28em] text-[var(--accent)]"
                        >
                            Al-Ihsan Savings Fund
                        </p>
                        <p class="text-sm text-[var(--muted)]">
                            স্বচ্ছ savings collection, deposit verification, ও
                            member ledger
                        </p>
                    </div>
                </div>

                <nav v-if="canLogin" class="flex flex-wrap items-center gap-3">
                    <a href="#overview" class="nav-link">Overview</a>
                    <a href="#flow" class="nav-link">Process</a>

                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="button-primary"
                    >
                        Dashboard
                    </Link>

                    <template v-else>
                        <Link :href="route('login')" class="button-secondary">
                            Log in
                        </Link>

                        <Link
                            v-if="canRegister"
                            :href="route('register')"
                            class="button-primary"
                        >
                            Register
                        </Link>
                    </template>
                </nav>
            </header>

            <main class="relative mt-8 flex-1">
                <section
                    class="grid gap-8 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:gap-12"
                >
                    <div class="space-y-8">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-[var(--line)] bg-white/80 px-4 py-2 text-sm text-[var(--ink)] shadow-[0_12px_30px_rgba(16,53,42,0.08)]"
                        >
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-[var(--gold)]"
                            ></span>
                            Trust-first digital savings management
                        </div>

                        <div class="space-y-5">
                            <p
                                class="font-display text-sm font-semibold uppercase tracking-[0.34em] text-[var(--accent)]"
                            >
                                Member transparency first
                            </p>

                            <h1
                                class="font-display max-w-3xl text-4xl font-extrabold leading-tight text-[var(--ink)] sm:text-5xl lg:text-6xl"
                            >
                                সদস্যদের জন্য পরিষ্কার,
                                <span class="text-[var(--accent)]"
                                    >অডিটযোগ্য</span
                                >
                                এবং নির্ভরযোগ্য savings homepage
                            </h1>

                            <p
                                class="max-w-2xl text-lg leading-8 text-[var(--muted)]"
                            >
                                ISF এমন একটি savings-focused platform, যেখানে
                                প্রতিটি member-এর deposit, approval status,
                                dues, আর ledger history পরিষ্কারভাবে দেখা যায়।
                                অপ্রয়োজনীয় complexity ছাড়াই fund management-কে
                                রাখা হয়েছে simple, traceable, এবং maintainable।
                            </p>
                        </div>

                        <div class="flex flex-col gap-4 sm:flex-row">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="button-primary justify-center"
                            >
                                Dashboard খুলুন
                            </Link>

                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="button-primary justify-center"
                                >
                                    Member Log in
                                </Link>

                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="button-secondary justify-center"
                                >
                                    নতুন সদস্য শুরু করুন
                                </Link>
                            </template>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <article
                                v-for="item in quickStats"
                                :key="item.label"
                                class="rounded-[24px] border border-[var(--line)] bg-white/80 p-5 shadow-[0_18px_40px_rgba(16,53,42,0.08)]"
                            >
                                <p
                                    class="font-display text-3xl font-extrabold text-[var(--ink)]"
                                >
                                    {{ item.value }}
                                </p>
                                <p
                                    class="mt-3 text-sm font-semibold text-[var(--ink)]"
                                >
                                    {{ item.label }}
                                </p>
                                <p
                                    class="mt-2 text-sm leading-6 text-[var(--muted)]"
                                >
                                    {{ item.detail }}
                                </p>
                            </article>
                        </div>
                    </div>

                    <div class="relative lg:pl-8">
                        <div
                            class="hero-card rounded-[32px] border border-[rgba(255,255,255,0.25)] p-6 sm:p-7"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="font-display text-sm font-semibold uppercase tracking-[0.28em] text-white/70"
                                    >
                                        Live member view
                                    </p>
                                    <h2
                                        class="mt-3 font-display text-3xl font-bold text-white"
                                    >
                                        জমা, status, ও ledger এক ফ্রেমে
                                    </h2>
                                </div>

                                <div
                                    class="rounded-full border border-white/20 bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-white/80"
                                >
                                    Verified flow
                                </div>
                            </div>

                            <div
                                class="mt-8 rounded-[26px] bg-white/10 p-5 ring-1 ring-white/10 backdrop-blur-sm"
                            >
                                <div
                                    class="flex items-center justify-between border-b border-white/10 pb-4"
                                >
                                    <div>
                                        <p class="text-sm text-white/70">
                                            Member balance
                                        </p>
                                        <p
                                            class="mt-2 font-display text-4xl font-extrabold text-white"
                                        >
                                            ৳ 24,500
                                        </p>
                                    </div>

                                    <div
                                        class="rounded-2xl bg-[var(--gold)] px-4 py-3 text-right text-[var(--ink)] shadow-[0_16px_34px_rgba(213,163,79,0.25)]"
                                    >
                                        <p
                                            class="text-xs font-semibold uppercase tracking-[0.2em]"
                                        >
                                            Deposit status
                                        </p>
                                        <p class="mt-1 text-lg font-bold">
                                            Pending review
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-5 space-y-3">
                                    <div
                                        class="rounded-2xl bg-white/8 px-4 py-3"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-white"
                                                >
                                                    মার্চ savings deposit
                                                </p>
                                                <p
                                                    class="mt-1 text-xs text-white/60"
                                                >
                                                    Submitted by member
                                                </p>
                                            </div>
                                            <span
                                                class="rounded-full bg-white/12 px-3 py-1 text-xs font-semibold text-white/80"
                                                >Pending</span
                                            >
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-2xl bg-white/8 px-4 py-3"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <div>
                                                <p
                                                    class="text-sm font-semibold text-white"
                                                >
                                                    ফেব্রুয়ারি verified entry
                                                </p>
                                                <p
                                                    class="mt-1 text-xs text-white/60"
                                                >
                                                    Approved and posted to
                                                    ledger
                                                </p>
                                            </div>
                                            <span
                                                class="rounded-full bg-emerald-300/20 px-3 py-1 text-xs font-semibold text-emerald-100"
                                                >Approved</span
                                            >
                                        </div>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div
                                            class="rounded-2xl bg-white px-4 py-4 text-[var(--ink)]"
                                        >
                                            <p
                                                class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--muted)]"
                                            >
                                                Due this month
                                            </p>
                                            <p
                                                class="mt-2 font-display text-2xl font-bold"
                                            >
                                                ৳ 1,000
                                            </p>
                                        </div>

                                        <div
                                            class="rounded-2xl bg-white px-4 py-4 text-[var(--ink)]"
                                        >
                                            <p
                                                class="text-xs font-semibold uppercase tracking-[0.18em] text-[var(--muted)]"
                                            >
                                                Ledger entries
                                            </p>
                                            <p
                                                class="mt-2 font-display text-2xl font-bold"
                                            >
                                                128
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="floating-note left-0 top-10 hidden -translate-x-1/3 xl:block"
                        >
                            জমা approve হলে history append হবে, delete বা
                            overwrite নয়
                        </div>

                        <div
                            class="floating-note right-4 top-full mt-4 hidden xl:block"
                        >
                            এক user, একাধিক family member profile
                        </div>
                    </div>
                </section>

                <section id="overview" class="mt-20 grid gap-5 md:grid-cols-3">
                    <article
                        v-for="card in featureCards"
                        :key="card.title"
                        class="rounded-[28px] border border-[var(--line)] bg-[var(--surface)] p-6 shadow-[0_20px_44px_rgba(16,53,42,0.08)]"
                    >
                        <div
                            class="mb-5 flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--accent-soft)] font-display text-lg font-bold text-[var(--accent)]"
                        >
                            {{ card.title.slice(0, 1) }}
                        </div>
                        <h3
                            class="font-display text-2xl font-bold text-[var(--ink)]"
                        >
                            {{ card.title }}
                        </h3>
                        <p class="mt-4 text-base leading-7 text-[var(--muted)]">
                            {{ card.copy }}
                        </p>
                    </article>
                </section>

                <section
                    id="flow"
                    class="mt-20 grid gap-8 lg:grid-cols-[0.95fr_1.05fr]"
                >
                    <div
                        class="rounded-[30px] border border-[var(--line)] bg-white/75 p-7 shadow-[0_24px_50px_rgba(16,53,42,0.08)]"
                    >
                        <p
                            class="font-display text-sm font-semibold uppercase tracking-[0.28em] text-[var(--accent)]"
                        >
                            Workflow
                        </p>
                        <h2
                            class="mt-4 font-display text-3xl font-bold text-[var(--ink)] sm:text-4xl"
                        >
                            Operational flow যত simple, তত বেশি trust
                        </h2>
                        <p
                            class="mt-4 max-w-xl text-base leading-7 text-[var(--muted)]"
                        >
                            ISF-এর প্রথম রিলিজে priority হচ্ছে savings
                            collection, deposit verification, ledgers, reports,
                            এবং member transparency। তাই workflow-এ unnecessary
                            abstraction রাখা হয়নি।
                        </p>

                        <div class="mt-8 space-y-4">
                            <div
                                v-for="(step, index) in steps"
                                :key="step.title"
                                class="flex gap-4 rounded-[22px] border border-[var(--line)] bg-[var(--surface)] p-4"
                            >
                                <div
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-[var(--ink)] font-display text-sm font-bold text-white"
                                >
                                    0{{ index + 1 }}
                                </div>
                                <div>
                                    <h3
                                        class="font-display text-xl font-bold text-[var(--ink)]"
                                    >
                                        {{ step.title }}
                                    </h3>
                                    <p
                                        class="mt-2 text-sm leading-6 text-[var(--muted)]"
                                    >
                                        {{ step.copy }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-5">
                        <article
                            class="rounded-[30px] bg-[var(--ink)] p-7 text-white shadow-[0_28px_60px_rgba(16,53,42,0.2)]"
                        >
                            <p
                                class="font-display text-sm font-semibold uppercase tracking-[0.28em] text-[var(--gold)]"
                            >
                                Why this homepage
                            </p>
                            <h2 class="mt-4 font-display text-3xl font-bold">
                                Financial clarity should feel visible from the
                                first screen
                            </h2>
                            <p
                                class="mt-4 max-w-xl text-base leading-7 text-white/72"
                            >
                                Homepage-এর design language এমন রাখা হয়েছে যাতে
                                trust, order, এবং transparency একসঙ্গে অনুভূত
                                হয়। তাই warm neutral background, deep green
                                panels, এবং ledger-inspired cards ব্যবহার করা
                                হয়েছে।
                            </p>
                        </article>

                        <article
                            class="rounded-[30px] border border-[var(--line)] bg-white/80 p-7 shadow-[0_24px_50px_rgba(16,53,42,0.08)]"
                        >
                            <p
                                class="font-display text-sm font-semibold uppercase tracking-[0.28em] text-[var(--accent)]"
                            >
                                Trust signals
                            </p>
                            <div class="mt-6 grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="signal in trustSignals"
                                    :key="signal"
                                    class="rounded-[20px] bg-[var(--surface)] px-4 py-4 text-sm font-semibold text-[var(--ink)]"
                                >
                                    {{ signal }}
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="mt-20">
                    <div
                        class="rounded-[34px] border border-[var(--line)] bg-gradient-to-r from-white via-[var(--surface)] to-[var(--accent-soft)] p-8 shadow-[0_28px_70px_rgba(16,53,42,0.1)] lg:flex lg:items-center lg:justify-between lg:gap-8"
                    >
                        <div>
                            <p
                                class="font-display text-sm font-semibold uppercase tracking-[0.28em] text-[var(--accent)]"
                            >
                                Ready to move forward
                            </p>
                            <h2
                                class="mt-4 font-display text-3xl font-bold text-[var(--ink)] sm:text-4xl"
                            >
                                সদস্যদের জন্য একটি পরিষ্কার digital front door
                                তৈরি হলো
                            </h2>
                            <p
                                class="mt-4 max-w-2xl text-base leading-7 text-[var(--muted)]"
                            >
                                পরের ধাপে member dashboard, deposit submission
                                flow, অথবা admin verification screens একই design
                                direction ধরে build করা যাবে।
                            </p>
                        </div>

                        <div
                            class="mt-6 flex flex-col gap-4 sm:flex-row lg:mt-0"
                        >
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="button-primary justify-center"
                            >
                                Dashboard এ যান
                            </Link>

                            <template v-else>
                                <Link
                                    :href="route('login')"
                                    class="button-primary justify-center"
                                >
                                    Log in
                                </Link>

                                <Link
                                    v-if="canRegister"
                                    :href="route('register')"
                                    class="button-secondary justify-center"
                                >
                                    Register
                                </Link>
                            </template>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>
</template>

<style scoped>
:global(:root) {
    --canvas: #f5efe4;
    --surface: #fffaf1;
    --accent-soft: #dcece2;
    --ink: #10352a;
    --accent: #1e6a50;
    --muted: #5d6b63;
    --gold: #d6a155;
    --line: rgba(16, 53, 42, 0.12);
}

.homepage-shell {
    position: relative;
    background:
        radial-gradient(
            circle at top left,
            rgba(214, 161, 85, 0.18),
            transparent 28%
        ),
        radial-gradient(
            circle at top right,
            rgba(30, 106, 80, 0.14),
            transparent 24%
        ),
        linear-gradient(180deg, #f8f2e9 0%, #f4eee4 52%, #f9f5ef 100%);
    color: var(--ink);
    font-family: "Noto Sans Bengali", "Segoe UI", sans-serif;
}

.page-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(16px);
    pointer-events: none;
}

.page-orb-left {
    top: 4rem;
    left: -5rem;
    height: 18rem;
    width: 18rem;
    background: rgba(214, 161, 85, 0.16);
}

.page-orb-right {
    top: 12rem;
    right: -4rem;
    height: 22rem;
    width: 22rem;
    background: rgba(30, 106, 80, 0.12);
}

.font-display {
    font-family: "Manrope", "Noto Sans Bengali", sans-serif;
}

.button-primary,
.button-secondary,
.nav-link {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 9999px;
    font-size: 0.95rem;
    font-weight: 700;
    transition:
        transform 180ms ease,
        box-shadow 180ms ease,
        background-color 180ms ease,
        color 180ms ease;
}

.button-primary {
    background: var(--ink);
    color: white;
    padding: 0.95rem 1.4rem;
    box-shadow: 0 18px 36px rgba(16, 53, 42, 0.18);
}

.button-primary:hover,
.button-secondary:hover,
.nav-link:hover {
    transform: translateY(-1px);
}

.button-secondary {
    border: 1px solid var(--line);
    background: rgba(255, 255, 255, 0.72);
    color: var(--ink);
    padding: 0.95rem 1.4rem;
}

.nav-link {
    color: var(--muted);
    padding: 0.7rem 1rem;
}

.hero-card {
    position: relative;
    overflow: hidden;
    background:
        linear-gradient(
            160deg,
            rgba(255, 255, 255, 0.12) 0%,
            rgba(255, 255, 255, 0.04) 100%
        ),
        linear-gradient(180deg, #0f3a2d 0%, #153c31 100%);
    box-shadow: 0 38px 90px rgba(16, 53, 42, 0.22);
}

.hero-card::before {
    content: "";
    position: absolute;
    inset: -40% auto auto 45%;
    height: 14rem;
    width: 14rem;
    border-radius: 9999px;
    background: rgba(214, 161, 85, 0.16);
    filter: blur(20px);
}

.floating-note {
    position: absolute;
    max-width: 14rem;
    border: 1px solid rgba(16, 53, 42, 0.08);
    border-radius: 1.25rem;
    background: rgba(255, 250, 241, 0.94);
    padding: 0.9rem 1rem;
    font-size: 0.82rem;
    font-weight: 700;
    line-height: 1.5;
    color: var(--ink);
    box-shadow: 0 18px 40px rgba(16, 53, 42, 0.1);
    backdrop-filter: blur(10px);
}

@media (max-width: 640px) {
    .homepage-shell {
        font-size: 15px;
    }
}
</style>
